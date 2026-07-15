<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

if (!class_exists('ZipArchive')) {
    die('ZipArchive extension is required for Excel export.');
}

$type = $_GET['type'] ?? 'daily';

class XlsxWriter
{
    private $sheets = [];
    private $strings = [];
    private $stringIndex = [];

    public function addSheet(string $name, array $headers, array $rows, array $colWidths = [])
    {
        $this->sheets[] = compact('name', 'headers', 'rows', 'colWidths');
    }

    private function escapeXml(string $val): string
    {
        return htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
    }

    private function getStringIndex(string $val): int
    {
        if (!isset($this->stringIndex[$val])) {
            $this->stringIndex[$val] = count($this->strings);
            $this->strings[] = $val;
        }
        return $this->stringIndex[$val];
    }

    private function generateSheetXml(array $sheet, int $sheetIndex): string
    {
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];
        $cols = count($headers);
        $rowCount = count($rows);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $xml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetFormatProperties defaultRowHeight="15"/>';

        if (!empty($sheet['colWidths'])) {
            $xml .= '<cols>';
            foreach ($sheet['colWidths'] as $i => $w) {
                $colNum = $i + 1;
                $xml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        $xml .= '<row r="1" s="1">';
        foreach ($headers as $colIdx => $header) {
            $ref = $this->colLetter($colIdx) . '1';
            $si = $this->getStringIndex($header);
            $xml .= '<c r="' . $ref . '" t="s"><v>' . $si . '</v></c>';
        }
        $xml .= '</row>';

        for ($r = 0; $r < $rowCount; $r++) {
            $rowNum = $r + 2;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($rows[$r] as $colIdx => $cell) {
                $ref = $this->colLetter($colIdx) . $rowNum;
                if (is_numeric($cell) && $cell !== '') {
                    $xml .= '<c r="' . $ref . '"><v>' . $this->escapeXml($cell) . '</v></c>';
                } else {
                    $si = $this->getStringIndex((string) $cell);
                    $xml .= '<c r="' . $ref . '" t="s"><v>' . $si . '</v></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function colLetter(int $index): string
    {
        $result = '';
        while ($index >= 0) {
            $result = chr(65 + ($index % 26)) . $result;
            $index = intdiv($index, 26) - 1;
        }
        return $result;
    }

    public function save(string $filePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            die('Failed to create XLSX file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());

        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $relsXml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $s) {
            $relsXml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $relsXml .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $relsXml .= '<Relationship Id="rId' . (count($this->sheets) + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $relsXml .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsXml);

        foreach ($this->sheets as $i => $sheet) {
            $sheetXml = $this->generateSheetXml($sheet, $i);
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $sheetXml);
        }

        $styleXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $styleXml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $styleXml .= '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>';
        $styleXml .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
        $styleXml .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
        $styleXml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $styleXml .= '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>';
        $styleXml .= '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styleXml);

        $allStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $allStrings .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($this->strings) . '" uniqueCount="' . count($this->strings) . '">';
        foreach ($this->strings as $str) {
            $allStrings .= '<si><t>' . $this->escapeXml($str) . '</t></si>';
        }
        $allStrings .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $allStrings);

        $zip->close();
    }

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $s) {
            $n = $i + 1;
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        $xml .= '</Types>';
        return $xml;
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $xml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($this->sheets as $i => $s) {
            $xml .= '<sheet name="' . $this->escapeXml($s['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }
}

function sendXlsx(string $filename, array $headers, array $rows, array $colWidths = [], string $sheetName = 'Sheet1'): void
{
    $writer = new XlsxWriter();
    $writer->addSheet($sheetName, $headers, $rows, $colWidths);
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
    $writer->save($tmpFile);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

switch ($type) {

    case 'daily':
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT u.full_name AS customer_name, s.name AS service_name, st.name AS staff_name,
                   a.appointment_time, s.price AS service_price
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE a.appointment_date = ? AND a.status = 'completed'
            ORDER BY a.appointment_time
        ");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll();
        $total = array_sum(array_column($rows, 'service_price'));

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r['customer_name'],
                $r['service_name'],
                $r['staff_name'],
                date('h:i A', strtotime($r['appointment_time'])),
                number_format($r['service_price'], 2),
            ];
        }
        $tableRows[] = ['', '', '', 'Total:', number_format($total, 2)];

        sendXlsx(
            'Daily_Report_' . $date . '.xlsx',
            ['Customer', 'Service', 'Staff', 'Time', 'Amount (MMK)'],
            $tableRows,
            [25, 25, 25, 15, 18],
            'Daily Report - ' . date('M d, Y', strtotime($date))
        );
        break;

    case 'monthly':
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("
            SELECT a.appointment_date, u.full_name AS customer_name, s.name AS service_name,
                   st.name AS staff_name, s.price AS service_price
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ? AND a.status = 'completed'
            ORDER BY a.appointment_date
        ");
        $stmt->execute([$month]);
        $rows = $stmt->fetchAll();
        $total = array_sum(array_column($rows, 'service_price'));

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                date('M d, Y', strtotime($r['appointment_date'])),
                $r['customer_name'],
                $r['service_name'],
                $r['staff_name'],
                number_format($r['service_price'], 2),
            ];
        }
        $tableRows[] = ['', '', '', 'Total:', number_format($total, 2)];

        sendXlsx(
            'Monthly_Report_' . $month . '.xlsx',
            ['Date', 'Customer', 'Service', 'Staff', 'Amount (MMK)'],
            $tableRows,
            [15, 25, 25, 25, 18],
            'Monthly Report - ' . date('F Y', strtotime($month . '-01'))
        );
        break;

    case 'appointments':
        $date_from = $_GET['date_from'] ?? date('Y-m-01');
        $date_to = $_GET['date_to'] ?? date('Y-m-d');
        $status_filter = $_GET['status'] ?? '';

        $where = "WHERE a.appointment_date BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        if ($status_filter !== '') {
            $where .= " AND a.status = ?";
            $params[] = $status_filter;
        }

        $stmt = $pdo->prepare("
            SELECT a.appointment_date, a.appointment_time,
                   u.full_name AS customer_name, u.phone AS customer_phone,
                   s.name AS service_name, s.price AS service_price,
                   st.name AS staff_name, a.status
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            $where
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                date('M d, Y', strtotime($r['appointment_date'])),
                date('h:i A', strtotime($r['appointment_time'])),
                $r['customer_name'],
                $r['customer_phone'] ?? '',
                $r['service_name'],
                $r['staff_name'],
                number_format($r['service_price'], 2),
                ucfirst(str_replace('_', ' ', $r['status'])),
            ];
        }

        sendXlsx(
            'Appointment_Report_' . date('Y-m-d') . '.xlsx',
            ['Date', 'Time', 'Customer', 'Phone', 'Service', 'Staff', 'Amount (MMK)', 'Status'],
            $tableRows,
            [15, 12, 22, 18, 22, 22, 18, 15],
            'Appointment Report'
        );
        break;

    case 'popular':
        $stmt = $pdo->query("
            SELECT s.name, c.name AS category_name, COUNT(a.id) AS booking_count, s.price,
                   COALESCE(SUM(s.price), 0) AS total_revenue
            FROM services s
            LEFT JOIN appointments a ON s.id = a.service_id AND a.status = 'completed'
            JOIN categories c ON s.category_id = c.id
            GROUP BY s.id
            ORDER BY booking_count DESC
        ");
        $popular = $stmt->fetchAll();

        $tableRows = [];
        foreach ($popular as $p) {
            $tableRows[] = [
                $p['name'],
                $p['category_name'],
                number_format($p['price'], 2),
                $p['booking_count'],
                number_format($p['total_revenue'], 2),
            ];
        }

        sendXlsx(
            'Popular_Services_Report_' . date('Y-m-d') . '.xlsx',
            ['Service', 'Category', 'Unit Price (MMK)', 'Bookings', 'Total Revenue (MMK)'],
            $tableRows,
            [30, 20, 18, 12, 20],
            'Popular Services'
        );
        break;

    case 'incentive':
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("
            SELECT st.id, st.name, st.specialization,
                   COALESCE(inc.rate, 10.00) AS rate,
                   COUNT(a.id) AS booking_count,
                   COALESCE(SUM(s.price), 0) AS total_revenue
            FROM staff st
            LEFT JOIN incentive_settings inc ON st.id = inc.staff_id
            LEFT JOIN appointments a ON st.id = a.staff_id AND a.status = 'completed'
                AND DATE_FORMAT(a.appointment_date, '%Y-%m') = ?
            LEFT JOIN services s ON a.service_id = s.id
            GROUP BY st.id
            ORDER BY st.name
        ");
        $stmt->execute([$month]);
        $staff_data = $stmt->fetchAll();

        $total_incentive = 0;
        $tableRows = [];
        foreach ($staff_data as $staff) {
            $rate = $staff['rate'] / 100;
            $incentive = $staff['total_revenue'] * $rate;
            $total_incentive += $incentive;
            $tableRows[] = [
                $staff['name'],
                $staff['specialization'] ?: 'N/A',
                ($rate * 100) . '%',
                $staff['booking_count'],
                number_format($staff['total_revenue'], 2),
                number_format($incentive, 2),
            ];
        }
        $tableRows[] = ['', '', '', '', 'Total Incentive:', number_format($total_incentive, 2)];

        sendXlsx(
            'Incentive_Report_' . $month . '.xlsx',
            ['Staff Name', 'Specialization', 'Rate', 'Bookings', 'Revenue (MMK)', 'Incentive (MMK)'],
            $tableRows,
            [25, 25, 10, 12, 18, 18],
            'Incentive Report - ' . date('F Y', strtotime($month . '-01'))
        );
        break;

    default:
        die('Invalid report type.');
}
