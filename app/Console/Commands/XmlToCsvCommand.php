<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use XMLReader;
use SimpleXMLElement;

class XmlToCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:xml-to-csv
                            {xml : Path to XML file}
                            {csv : Path to output CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert an XML feed into a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $xmlPath = $this->argument('xml');
    $csvPath = $this->argument('csv');

    if (!file_exists($xmlPath)) {
        $this->error("XML file not found: {$xmlPath}");
        return self::FAILURE;
    }

    // Read XML
    $content = file_get_contents($xmlPath);

    // Replace HTML non-breaking spaces
    $content = str_replace('&nbsp;', '&#160;', $content);

    // Escape invalid ampersands while preserving valid entities
    $content = preg_replace(
        '/&(?!amp;|lt;|gt;|quot;|apos;|#[0-9]+;|#x[0-9A-Fa-f]+;)/',
        '&amp;',
        $content
    );

    // Save cleaned XML to a temporary file
    $tmpFile = tempnam(sys_get_temp_dir(), 'feed_');
    file_put_contents($tmpFile, $content);

    $reader = new XMLReader();

    if (!$reader->open($tmpFile)) {
        $this->error('Unable to open XML.');
        return self::FAILURE;
    }

    $csv = fopen($csvPath, 'w');

    $headersWritten = false;

    while ($reader->read()) {

        if (
            $reader->nodeType === XMLReader::ELEMENT &&
            $reader->localName === 'item'
        ) {

            $node = new SimpleXMLElement($reader->readOuterXML());

            // Google Shopping namespace
            $g = $node->children('http://base.google.com/ns/1.0');
            $additionalImages = [];

            foreach ($g->additional_image_link as $image) {
                $url = trim((string) $image);

                if ($url !== '') {
                    $additionalImages[] = $url;
                }
            }
            $row = [
                'id' => (string) $g->id,
                'title' => (string) $g->title,
                'description' => trim((string) $g->description),
                'item_group_id' => (string) $g->item_group_id,
                'link' => (string) $node->link,
                'product_type' => (string) $g->product_type,
                'google_product_category' => (string) $g->google_product_category,
                'image_link' => (string) $g->image_link,
                'condition' => (string) $g->condition,
                'availability' => (string) $g->availability,
                'price' => (string) $g->price,
                'sale_price' => (string) $g->sale_price,
                'mpn' => (string) $g->mpn,
                'canonical_link' => (string) $g->canonical_link,
                'additional_image_link' => implode(',', $additionalImages),
                'brand' => (string) $g->brand,
                'identifier_exists' => (string) $g->identifier_exists,
                'category' => 'Hardware > Building Consumables',
            ];

            if (!$headersWritten) {
                fputcsv($csv, array_keys($row));
                $headersWritten = true;
            }

            fputcsv($csv, $row);

            unset($node);
        }
    }

    fclose($csv);
    $reader->close();

    unlink($tmpFile);

    $this->info("CSV created successfully: {$csvPath}");

    return self::SUCCESS;
    }
}
