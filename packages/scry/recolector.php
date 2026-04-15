<?php
namespace Scry;
use Scry\PhpFile\PhpFile;
use Composer\Composer;

include_once 'phpfile/phpfile.php';

class Recolector
{
    public function All(string $directory, bool $recursive = false) : array
    {
        if(!is_dir($directory))
        {
            Composer::Throw("Directory not found: $directory. Please provide a valid directory path to scan.");
        }

        $classes = [];
        $directory = Composer::CorrectPath($directory);

        // Scan current directory
        foreach (glob($directory . '*.php') as $file)
        {
            $fileScanThread = new PhpFile($file);
            $fileScanThread->Tokenize();
            $fileScanThread->NextClass();
            
            $fileScanThread->BufferExport($classes);
        }

        // Recursively scan subdirectories
        if ($recursive)
        {
            foreach (glob($directory . '*', GLOB_ONLYDIR) as $subdir)
            {
                $classes += $this->All($subdir, true); // Faster than array_merge
            }
        }

        return $classes;
    }
}
