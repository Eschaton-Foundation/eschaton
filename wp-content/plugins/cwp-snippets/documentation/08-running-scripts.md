# Running FileMaker Scripts

The CWP Snippets plugin provides two main ways to execute a FileMaker script on your host server: directly, or as part of another data operation.

## Direct Execution with `runScript()`

The most straightforward way to run a script is by using the `$fm->runScript()` method. This is useful when you just need to trigger a script without performing another action like finding or creating a record.

This method takes two arguments:
1.  `$scriptName` (string, required): The name of the script you want to run.
2.  `$scriptParameters` (array, optional): An associative array containing parameters to pass to the script.

### Example:

```php
<?php
// Instantiate the connector
$fm = new fmCWP(FM_HOST_DEMO, FM_DATABASE_DEMO, FM_LAYOUT_DEMO, FM_USER_DEMO, FM_PASSWORD_DEMO);

// Define the script name
$scriptName = "Log request";

// Setting script parameters
$scriptParams = [
    "script.param" => "Parameter from CWP Snippets - run script"
];

// Run the script
$result = $fm->runScript($scriptName, $scriptParams);

// Process the result...
?>
```

## Triggering Scripts Within Data Calls

You can also trigger scripts to run as part of another API call, such as when you are finding, creating, or editing records. This is done by adding special keys to the `$params` array that you pass to methods like `findRecords()`, `createRecord()`, `getRecord()`, etc.

There are three types of script triggers available:

*   `script`: Runs a script *after* the main action is completed.
*   `script.prerequest`: Runs a script *before* the main action is performed.
*   `script.presort`: Used only with `findRecords()`, this runs a script *after* the records are found but *before* they are sorted.

For each trigger, you can pass a parameter by using the corresponding key with a `.param` suffix (e.g., `script.param`, `script.prerequest.param`).

### Example using `findRecords()`:

This example demonstrates using all three script triggers in a single find request.

```php
<?php
$fm = new fmCWP(FM_HOST_DEMO, FM_DATABASE_DEMO, FM_LAYOUT_DEMO, FM_USER_DEMO, FM_PASSWORD_DEMO);

$params = [
    "query" => [
        ["Email Addresses::Address" => "==ron@rgcdata.com"]
    ],

    // Script to run BEFORE the request
    "script.prerequest" => "Log Pre-Request",
    "script.prerequest.param" => "Parameter for pre-request script",

    // Script to run BEFORE the sort
    "script.presort" => "Log Pre-Sort",
    "script.presort.param" => "Parameter for pre-sort script",

    // Script to run AFTER the action
    "script" => "Log Post-Action",
    "script.param" => "Parameter for post-action script"
];

$result = $fm->findRecords($params);

// Process the result...
?>
```

## Warning: Large Script Parameters

Caution is advised when sending large amounts of data as script parameters, particularly if your FileMaker server is hosted on a Windows environment. This can lead to difficult-to-diagnose failures.

### The Problem

When a script is executed with a very large parameter, the underlying request to the FileMaker Data API can become too long. Web servers and the FileMaker Web Publishing Engine have limits on the length of URLs they will process. Exceeding this limit will not produce a clear error from FileMaker; instead, it typically results in a generic `404 Not Found` HTTP error.

This can be misleading, as the script and layout names are correct, but the request is rejected before the Data API can even process it.

**Example Error Response:**
```php
Array
(
    [status] => Array
        (
            [http_code] => 404
        )

    [result] => 
)
```

### The Solution

If you need to pass a large amount of data to a script, the recommended workaround is to avoid passing it directly in the script parameter. A more reliable method is:

1.  Use the `createRecord()` method to create a new record in a designated table in your FileMaker database.
2.  Store the large data payload in one or more fields of this new record.
3.  Use the `script` parameter within your `createRecord()` call to trigger a script that runs *after* the record is created.
4.  This post-creation script can then access the data from the fields of the new record and perform the required actions.

This approach avoids the URL length limitation by transferring the data within the body of a `create` request and then processing it server-side within FileMaker.```
