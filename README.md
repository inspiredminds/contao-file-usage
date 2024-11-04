[![](https://img.shields.io/packagist/v/inspiredminds/contao-file-usage.svg)](https://packagist.org/packages/inspiredminds/contao-file-usage)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-file-usage.svg)](https://packagist.org/packages/inspiredminds/contao-file-usage)

Contao File Usage
=================

This Contao extension allows you to find and display file references of your files managed by the file manager. For each
file in the file manager there will be a new operation (<img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/public/search.svg" alt="search"> or <img src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/public/link.svg" alt="link">).

<img width="520" src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/filemanager.png" alt="File manager">

This operation will then show you any references of this file that this extension finds in the database, with links to
the original data record, if available.

<img width="520" src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/references.png" alt="References">

The search results are cached indefinitely. You can force to fetch new search results with the _Refresh_ button.
However, depending on the size of your database this might take a while and might not be able to finish within the
HTTP request. In this case you will need to rely on the [cronjob](#cronjob) or [command](#command).

You can also use the "Unused files" global operation in order to find any database assisted files that aren't referenced
anywhere (at least according to the search results).

<img width="520" src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/unused.png" alt="Unused files">

## File Replacements

This extension also replaces Contao's `fileTree` widget with its own implementation, showing an additional button with 
which you can replace the file references found for this file with a new reference.

<img width="520" src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/gallery.png" alt="Gallery">

<img width="520" src="https://raw.githubusercontent.com/inspiredminds/contao-file-usage/master/replace.png" alt="Replace references">

## Cronjob

As previously mentioned the search results are cached. In order for the cache to always be up to date for
at least 24 hours this extension implements a daily cronjob. However, the cronjob is only run on the command line
interface, so make sure that you have set up [Contao's cronjob](https://docs.contao.org/dev/framework/cron/) 
accordingly.

## Command

You can also warm up the file usage result cache from the command line, using the `contao_file_usage:warmup` command.

## Custom Providers

Currently this extension can find any references created by the `fileTree` input field of any (database based) DCA and 
it can find any references from `{{file::*}}`, `{{picture::*}}` and `{{figure::*}}` insert tags in any text based fields 
in the database. If you want to expand this search to other locations you can implement your own _file usage provider_ 
by implementing the `FileUsageProviderInterface`.

```php
// src/FileUsage/FoobarProvider.php
use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

class FoobarProvider implements FileUsageProviderInterface
{
    public function find(): ResultsCollection
    {
        $collection = new ResultsCollection();

        // Additional database search
        // …

        $collection->addResult(new DatabaseReferenceResult($table, $field, $id));

        return $collection;
    }
}
```

That is all you need to do, if you enabled `autoconfigure` for your service. Otherwise you will also need to tag the 
service with `contao_file_usage.provider` manually.

You might want or need to implement a new result container using the `ResultInterface` for your purposes (e.g. if your 
provider looks in the contents of files, rather than the database for example, which this extension currently does not 
do by default). In your interface you will need to reference the Twig template that should be used when rendering the
result in the back end:

```php
// src/FilesUsage/CustomFileUsageResult.php
use InspiredMinds\ContaoFileUsage\Result\ResultInterface;

class CustomFileUsageResult implements ResultInterface
{
    public function __construct(
        // Your result data
    ) {
    }

    public function getTemplate(): string
    {
        return '@Foobar/my_file_usage_result.html.twig';
    }
}
```
