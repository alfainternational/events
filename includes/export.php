<?php
/**
 * نظام تصدير التقارير - Export System
 * CSV, Excel (XLSX), PDF
 */

class ReportExporter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تصدير إلى CSV
     */
    public function exportToCSV($filters = []) {
        $events = $this->getEvents($filters);
        
        // إعداد الـ headers
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="events_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // BOM لـ UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // العناوين
        fputcsv($output, [
            'ID', 'العنوان', 'الجهة المنظمة', 'التاريخ', 'الوقت', 
            'النوع', 'القاعة', 'الحالة', 'الجوال', 'البريد'
        ]);
        
        // البيانات
        foreach ($events as $event) {
            fputcsv($output, [
                $event['id'],
                $event['title'],
                $event['organizing_dept'],
                $event['start_date'],
                $event['start_time'],
                $event['location_type'] == 'internal' ? 'داخلية' : 'خارجية',
                $event['hall_name'] ?? $event['custom_hall_name'] ?? 'N/A',
                $event['status'],
                $event['requester_mobile'],
                $event['requester_email']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    /**
     * تصدير إلى Excel (XLSX) باستخدام XML
     */
    public function exportToExcel($filters = []) {
        $events = $this->getEvents($filters);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="events_' . date('Y-m-d') . '.xlsx"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // إنشاء ملف Excel بسيط باستخدام XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . PHP_EOL;
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . PHP_EOL;
        $xml .= '<Worksheet ss:Name="Events"><Table>' . PHP_EOL;
        
        // العناوين
        $xml .= '<Row>';
        foreach (['ID', 'العنوان', 'الجهة المنظمة', 'التاريخ', 'الوقت', 'النوع', 'القاعة', 'الحالة'] as $header) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
        }
        $xml .= '</Row>' . PHP_EOL;
        
        // البيانات
        foreach ($events as $event) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="Number">' . $event['id'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($event['title']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($event['organizing_dept']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $event['start_date'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $event['start_time'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($event['location_type'] == 'internal' ? 'داخلية' : 'خارجية') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($event['hall_name'] ?? $event['custom_hall_name'] ?? 'N/A') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $event['status'] . '</Data></Cell>';
            $xml .= '</Row>' . PHP_EOL;
        }
        
        $xml .= '</Table></Worksheet></Workbook>';
        
        echo $xml;
        exit();
    }
    
    /**
     * تصدير إلى PDF (HTML to PDF بسيط)
     */
    public function exportToPDF($filters = []) {
        $events = $this->getEvents($filters);
        
        // سنستخدم HTML مع CSS للطباعة
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="events_' . date('Y-m-d') . '.pdf"');
        
        // في الواقع، لإنشاء PDF حقيقي بدون مكتبات، سنستخدم TCPDF أو mPDF
        // لكن كبديل مؤقت، سنستخدم HTML printable
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="events_' . date('Y-m-d') . '.html"');
        
        echo $this->generatePrintableHTML($events);
        exit();
    }
    
    /**
     * توليد HTML قابل للطباعة
     */
    private function generatePrintableHTML($events) {
        $html = '<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الفعاليات - ' . date('Y-m-d') . '</title>
    <style>
        @media print { @page { size: A4 landscape; margin: 20mm; } }
        body { font-family: Arial, sans-serif; direction: rtl; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        th { background: #14b8a6; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f0f9ff; }
        h1 { text-align: center; color: #0f766e; }
        .header { text-align: center; margin-bottom: 20px; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #666; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>تقرير الفعاليات</h1>
        <p>تاريخ الإصدار: ' . date('Y-m-d H:i') . '</p>
        <p>عدد الفعاليات: ' . count($events) . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>العنوان</th>
                <th>الجهة المنظمة</th>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>القاعة</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($events as $event) {
            $html .= '<tr>';
            $html .= '<td>' . $event['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($event['title']) . '</td>';
            $html .= '<td>' . htmlspecialchars($event['organizing_dept']) . '</td>';
            $html .= '<td>' . $event['start_date'] . '</td>';
            $html .= '<td>' . ($event['location_type'] == 'internal' ? 'داخلية' : 'خارجية') . '</td>';
            $html .= '<td>' . htmlspecialchars($event['hall_name'] ?? $event['custom_hall_name'] ?? 'N/A') . '</td>';
            $html .= '<td>' . $event['status'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
    </table>
    <div class="footer">
        <p>كلية الشمال للتمريض الأهلية - نظام إدارة الفعاليات</p>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * جلب البيانات مع الفلاتر
     */
    private function getEvents($filters = []) {
        $where = ["deleted_at IS NULL"];
        $params = [];
        
        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['location_type'])) {
            $where[] = "location_type = ?";
            $params[] = $filters['location_type'];
        }
        
        if (isset($filters['date_from'])) {
            $where[] = "start_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = "start_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare("
            SELECT e.*, h.name as hall_name
            FROM events e
            LEFT JOIN halls h ON e.hall_id = h.id
            WHERE {$whereClause}
            ORDER BY e.start_date DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

/**
 * دوال مساعدة
 */
function export_to_csv($filters = []) {
    global $pdo;
    $exporter = new ReportExporter($pdo);
    $exporter->exportToCSV($filters);
}

function export_to_excel($filters = []) {
    global $pdo;
    $exporter = new ReportExporter($pdo);
    $exporter->exportToExcel($filters);
}

function export_to_pdf($filters = []) {
    global $pdo;
    $exporter = new ReportExporter($pdo);
    $exporter->exportToPDF($filters);
}
?>
