# Building Parameter Arrays

Most methods in the `fmCWP` class are configured by passing a `$params` array. This array can contain various keys depending on the action you want to perform. This guide covers the most common parameter structures.

## `fieldData` (for Creating & Editing)

The `fieldData` key is used with `createRecord()` and `editRecord()` to specify the field values for a record. It's a simple associative array where the keys are your FileMaker field names and the values are the data you want to insert.

### Example:

```php
$params = [
    "fieldData" => [
        "First Name" => "John",
        "Last Name" => "Smith",
        "Company" => "ACME Corp"
    ]
];

// To create a new record with this data:
$result = $fm->createRecord($params);

// To edit record with ID 123 with this data:
$result = $fm->editRecord(123, $params);
```

## `portalData` (for Portals)

The `portalData` key is used to create or update records in a portal on the target layout. The key for `portalData` is an associative array where each key is the **object name** of the portal in FileMaker.

-   **To create a new portal record:** Provide the field data for the new related record.
-   **To edit an existing portal record:** You must include the `recordId` of the specific portal row you wish to modify.

### Example:

```php
$params = [
    "fieldData" => [
        "First Name" => "Jane",
        "Last Name" => "Doe"
    ],
    "portalData" => [
        "Phone Numbers Portal" => [
            // Edit existing portal record with recordId 45
            [
                "recordId" => "45", 
                "Phone Numbers::Type" => "Mobile",
                "Phone Numbers::Number" => "555-123-4567"
            ],
            // Create a new portal record (no recordId)
            [
                "Phone Numbers::Type" => "Work",
                "Phone Numbers::Number" => "555-987-6543"
            ]
        ]
    ]
];

$result = $fm->editRecord(124, $params);
```

## `query` (for Finding Records)

The `query` key is used with `findRecords()` to define your find criteria. It is an array of associative arrays. Each inner array represents a find request.

-   To perform a logical **OR** find, create multiple inner arrays.
-   To perform a logical **AND** find, add multiple field criteria to the *same* inner array.
-   You can also add an `"omit" => "true"` key to a request to exclude records matching that criteria.

### Example:

```php
// Finds records where (Company is "ACME Corp" AND City is "New York") OR (Company is "RGC Data LLC")
$params = [
    "query" => [
        // First find request (AND)
        [
            "Company" => "==ACME Corp",
            "City" => "==New York"
        ],
        // Second find request (OR)
        [
            "Company" => "==RGC Data LLC"
        ]
    ]
];

$result = $fm->findRecords($params);
```

## Pagination (`limit` & `offset`)

Pagination parameters control how many records are returned and from what starting point. The FileMaker API uses different keys for different endpoints. The `fmCWP` class handles this, but you need to use the correct key for the method you're calling.

-   **`findRecords()`** uses `limit` and `offset` inside the main `$params` array.
-   **`getRecords()`** uses `_limit` and `_offset` inside the main `$params` array. (Note: As of a recent update, this method also accepts `limit` and `offset` and will convert them for you).

### Example for `findRecords()`:

```php
$params = [
    "query" => [ ["Company" => "*"] ], // Find all companies
    "limit" => 10, // Return a maximum of 10 records
    "offset" => 20 // Skip the first 20 records
];
```

## Sorting (`sort`)

The `sort` parameter, used with `findRecords()`, allows you to specify the sort order for the found set. It is an array of associative arrays, where each inner array defines a sort field and direction.

### Example:

```php
$params = [
    "query" => [ ["Company" => "*"] ],
    "sort" => [
        // Primary sort: by State in ascending order
        [ "fieldName" => "State", "sortOrder" => "ascend" ], 
        // Secondary sort: by Company in descending order
        [ "fieldName" => "Company", "sortOrder" => "descend" ]
    ]
];
```
