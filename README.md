[![](https://img.shields.io/packagist/v/inspiredminds/contao-file-usage.svg)](https://packagist.org/packages/inspiredminds/contao-file-usage)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-file-usage.svg)](https://packagist.org/packages/inspiredminds/contao-file-usage)

Contao File Usage
=================

This Contao extension allows you to find and display file references of your files managed by the file manager. For each
file in the file manager there will be a new operation (<img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/public/search.svg" alt="search"> or <img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/public/link.svg" alt="link">).

<img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/filemanager.png" alt="File manager">

This operation will then show you any references of this file that this extension finds in the database, with links to
the original data record, if available.

<img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/references.png" alt="References">

You can also use the "Unused files" global operation in order to find any database assisted files that aren't referenced
anywhere (at least according to the search results).

<img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/unused.png" alt="Unused files">

## Custom Providers

Currently this extension can find any references created by the `fileTree` input field of any (database based) DCA and 
it can find any references from `{{file::*}}`, `{{picture::*}}` and `{{figure::*}}` insert tags in any text based fields 
in the database. If you want to expand this search to other locations you can implement your own _file usage provider_ 
by implementing the `FileUsageProviderInterface`.

```php
// src/FileUsage/FoobarProvider.php
use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\Results;

class FoobarProvider implements FileUsageProviderInterface
{
    public function find(string $uuid): Results
    {
        $results = new Results($uuid);

        // Additional database search
        // …

        $results->addResult(new DatabaseReferenceResult($table, $field, $id));

        return $results;
    }
}
```

That is all you need to do, if you enabled `autoconfigure` for your service. Otherwise you will also need to tag the 
service with `contao_file_usage.provider` manually.

You might want or need to implement a new result container using the `ResultInterface` for your purposes (e.g. if your 
provider looks in the contents of files, rather than the database for example, which this extension currently does not 
do by default).

## File Replacements

This extension also replaces Contao's `fileTree` widget with its own implementation, showing an additional button with 
which you can replace the file references found for this file with a new reference.
