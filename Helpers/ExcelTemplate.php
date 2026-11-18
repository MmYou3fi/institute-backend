<?php

declare(strict_types=1);

namespace Helpers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExcelTemplate
{
    public static function build(array $spec): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($spec['sheet']);
        $sheet->setRightToLeft(true);

        $lastLetter = 'A';

        foreach ($spec['columns'] as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $lastLetter = $letter;

            $sheet->setCellValue($letter . '1', $column['title']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
            $sheet->getStyle($letter . '2:' . $letter . (ExcelReader::MAX_ROWS + 1))
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->setCellValueExplicit($letter . '2', $column['example'], DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:' . $lastLetter . '1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('راهنما');
        $guide->setRightToLeft(true);
        $guide->getColumnDimension('A')->setWidth(100);

        foreach ($spec['guide'] as $index => $line) {
            $guide->setCellValue('A' . ($index + 1), $line);
        }

        $spreadsheet->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return $content;
    }
}