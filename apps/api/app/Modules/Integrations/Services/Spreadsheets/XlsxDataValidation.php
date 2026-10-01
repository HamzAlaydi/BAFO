<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Spreadsheets;

use RuntimeException;
use ZipArchive;

/**
 * Adds list data validations (dropdowns) to the first sheet of an XLSX file written by openspout,
 * which cannot write them itself (the import template of ARCHITECTURE §14.7).
 */
final class XlsxDataValidation
{
    /**
     * @param  list<array{range: string, source: string, strict: bool}>  $lists  e.g. `J2:J10001` → `Lists!$A$2:$A$14`;
     *                                                                           not strict = a suggestion only
     */
    public static function addLists(string $path, array $lists): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open the XLSX file.');
        }

        $entry = 'xl/worksheets/sheet1.xml';
        $xml = $zip->getFromName($entry);

        if ($xml === false) {
            $zip->close();

            throw new RuntimeException('The XLSX file has no first sheet.');
        }

        $validations = '';

        foreach ($lists as $list) {
            $validations .= sprintf(
                '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="%d" sqref="%s"><formula1>%s</formula1></dataValidation>',
                $list['strict'] ? 1 : 0,
                htmlspecialchars($list['range'], ENT_XML1),
                htmlspecialchars($list['source'], ENT_XML1),
            );
        }

        $block = '<dataValidations count="'.count($lists).'">'.$validations.'</dataValidations>';

        // CT_Worksheet order: dataValidations come after sheetData (and mergeCells), before pageMargins.
        $xml = str_contains($xml, '<pageMargins')
            ? (string) preg_replace('/<pageMargins/', $block.'<pageMargins', $xml, 1)
            : str_replace('</worksheet>', $block.'</worksheet>', $xml);

        $zip->addFromString($entry, $xml);
        $zip->close();
    }
}
