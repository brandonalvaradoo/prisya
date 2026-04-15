<?php

class SimpleZip
{
    private array $files = [];
    private string $centralDirectory = "";
    private int $offset = 0;

    public function addFile(string $data, string $name)
    {
        $crc = crc32($data);
        $compressed = gzcompress($data);
        $compressed = substr($compressed, 2, -4);

        $size = strlen($data);
        $csize = strlen($compressed);

        $header = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            8,
            0,
            0,
            $crc,
            $csize,
            $size,
            strlen($name),
            0
        );

        $this->files[] = $header . $name . $compressed;

        $central = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0,
            20,
            0,
            8,
            0,
            0,
            $crc,
            $csize,
            $size,
            strlen($name),
            0,
            0,
            0,
            0,
            32,
            $this->offset
        );

        $this->centralDirectory .= $central . $name;
        $this->offset += strlen($header . $name . $compressed);
    }

    public function output(): string
    {
        $data = implode("", $this->files);
        $cdSize = strlen($this->centralDirectory);

        $footer = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($this->files),
            count($this->files),
            $cdSize,
            $this->offset,
            0
        );

        return $data . $this->centralDirectory . $footer;
    }
}
