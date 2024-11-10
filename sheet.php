<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 初始化日誌數組
$logs = [];
function addLog($message, $type = 'info') {
    global $logs;
    $logs[] = [
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message
    ];
}

try {
    addLog('開始處理請求');
    
    // 使用發布的 URL
    $publishedUrl = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSSW1dj8DNuzIHc4k58RXosX1rmzsY9F-5bkLwPDvYE62SXPODz2rO2zJIcT8uidA/pubhtml";
    addLog("嘗試訪問發布的試算表: {$publishedUrl}");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $publishedUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true  // 獲取響應頭
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // 獲取最後修改時間
    $lastModified = curl_getinfo($ch, CURLINFO_FILETIME);
    if ($lastModified == -1) {
        // 如果無法從 curl 獲取，嘗試從響應頭獲取
        if (preg_match('/Last-Modified: (.+)/', $response, $matches)) {
            $lastModified = strtotime($matches[1]);
        }
    }

    addLog("HTTP 回應碼: {$httpCode}");

    if ($response === false) {
        addLog("cURL 錯誤: " . curl_error($ch), 'error');
        throw new Exception('無法訪問試算表');
    }

    curl_close($ch);

    // 從 HTML 回應中提取工作表名稱
    $sheets = [];
    if (preg_match_all('/<li[^>]*><a[^>]*>([^<]+)<\/a><\/li>/', $response, $matches)) {
        foreach ($matches[1] as $sheetName) {
            $sheets[] = trim($sheetName);
            addLog("找到工作表: {$sheetName}", 'success');
        }
    }

    // 如果上面的方法失敗，嘗試另一種方式
    if (empty($sheets)) {
        addLog("嘗試使用備用方法", 'info');
        if (preg_match_all('/<span[^>]*>([^<]+)<\/span>/', $response, $matches)) {
            foreach ($matches[1] as $match) {
                if (strpos($match, '工作表') !== false || strpos($match, 'Test') !== false) {
                    $sheetName = trim($match);
                    if (!in_array($sheetName, $sheets)) {
                        $sheets[] = $sheetName;
                        addLog("找到工作表: {$sheetName}", 'success');
                    }
                }
            }
        }
    }

    if (empty($sheets)) {
        addLog("沒有找到任何工作表", 'error');
        throw new Exception('找不到工作表');
    }

    addLog("成功獲取工作表列表: " . implode(', ', $sheets), 'success');
    echo json_encode([
        'sheets' => $sheets,
        'lastModified' => $lastModified ? $lastModified * 1000 : null,  // 轉換為毫秒
        'logs' => $logs
    ]);

} catch (Exception $e) {
    addLog("錯誤: " . $e->getMessage(), 'error');
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'logs' => $logs
    ]);
}
?> 