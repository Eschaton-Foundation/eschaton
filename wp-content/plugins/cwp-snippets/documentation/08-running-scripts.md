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
