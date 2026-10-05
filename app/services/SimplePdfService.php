<?php

declare(strict_types=1);

namespace App;

final class SimplePdfService
{
    /**
     * @param list<string> $lines
     */
    public static function document(array $lines, string $title = 'Order Details'): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $wrapped = array_merge($wrapped, self::wrap($line, 92));
        }

        $pages = array_chunk($wrapped, 44);
        $objects = [];
        $pageIds = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [] /Count 0 >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pages as $pageIndex => $pageLines) {
            $pageId = count($objects) + 1;
            $contentId = $pageId + 1;
            $pageIds[] = $pageId;

            $stream = self::pageStream($pageLines, $title, $pageIndex + 1, count($pages));
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }

        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $line, int $width): array
    {
        if (trim($line) === '') {
            return [''];
        }

        return explode("\n", wordwrap($line, $width, "\n", true));
    }

    /**
     * @param list<string> $lines
     */
    private static function pageStream(array $lines, string $title, int $page, int $pages): string
    {
        $commands = ["BT", "/F1 18 Tf", "50 744 Td", '(' . self::pdfText($title) . ') Tj', "/F1 10 Tf", "0 -20 Td"];
        foreach ($lines as $line) {
            $commands[] = '(' . self::pdfText($line) . ') Tj';
            $commands[] = '0 -15 Td';
        }

        $commands[] = "0 -12 Td";
        $commands[] = '(' . self::pdfText("Page {$page} of {$pages}") . ') Tj';
        $commands[] = "ET";

        return implode("\n", $commands);
    }

    private static function pdfText(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
