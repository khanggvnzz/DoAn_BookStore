<?php

header('Content-Type: application/json');

function generateBankQR($amount, $content = "Book Store Payment")
{
    $url = 'https://api.vietqr.org/vqr/api/qr/generate/unauthenticated';

    // Dữ liệu gửi đi
    $data = [
        'bankAccount' => '1030382538',
        'userBankName' => 'VU BA NHAT KHANG',
        'bankCode' => 'VCB',
        'amount' => (string) $amount,
        'content' => $content
    ];

    // Headers
    $headers = [
        'Accept: */*',
        'Accept-Language: vi,en-US;q=0.9,en;q=0.8',
        'Access-Control-Allow-Credentials: true',
        'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept',
        'Access-Control-Allow-Methods: GET,PUT,PATCH,POST,DELETE',
        'Access-Control-Allow-Origin: *',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'Content-Type: application/json; charset=utf-8',
        'Origin: https://vietqr.vn',
        'Referer: https://vietqr.vn/',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: cross-site',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0',
        'sec-ch-ua: "Microsoft Edge";v="137", "Chromium";v="137", "Not/A)Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"'
    ];

    // Initialize cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode,
            'response' => $response
        ];
    }

    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'JSON Decode Error: ' . json_last_error_msg(),
            'raw_response' => $response
        ];
    }

    return [
        'success' => true,
        'data' => $responseData
    ];
}

function processQRCodeWithJsonVn($qrData)
{
    $url = 'https://json.vn/livewire/message/public.tools.qr-code-generator';

    $headers = [
        'Accept: text/html, application/xhtml+xml',
        'Accept-Language: vi,en-US;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/json',
        'Origin: https://json.vn',
        'Referer: https://json.vn/qr-code-generator',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0',
        'X-CSRF-TOKEN: YkN9oVn8VpchkCowhaWrA2F2mmeLfg0L4reRPtrK',
        'X-Livewire: true',
        'sec-ch-ua: "Microsoft Edge";v="137", "Chromium";v="137", "Not/A)Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"'
    ];

    $cookies = 'XSRF-TOKEN=eyJpdiI6IlFlNk9mTXE3dmJkZk83bldJYVFTQ3c9PSIsInZhbHVlIjoidTJYSWFrWHpJLzJia041RG9jTEVLSGZaSlJTdS91emNuVUlLVS9VeG5tZmVjcG14R1ZPTzEwdzhSUE54SnBxM0VBT29XdUdLQythV0RkQmwwZ3JLZ0xyQ3YzTHZQbnptVU14eVlwZS9hRm5QNmEram5WNDhRZ0FNZnB6Z2lxdGkiLCJtYWMiOiJiY2Q4N2Y1ODk5OWNjYzJjZDE3NjAxZTZiNWUxMTQ0NjAwYzdhYjU3NDFhNjc5NTAzMTY5MGExNDM3NWQ2NjUzIiwidGFnIjoiIn0%3D; json_tools_session=eyJpdiI6Iit1eVZWWlFQem9Wbm15RzRGdEJNYnc9PSIsInZhbHVlIjoiR3hnNk9IVUV5RjJxUWlPTnE2djlwYVY2MDNreHdIUEludjJkUzhXRHE4OUhML3BmekorbFBoNDg4T2hkQy9rb1lkbWgzK0pTdWxTd2ZoalNNT2hRSWErSjlIa2Z4QkF4OHgyTnN6eWVESVh4ckFROFlnNG1vQk1jRm1lTlJtbjgiLCJtYWMiOiJlMThmNTM0NmRkYWI2ZGJhYWZhN2RmYTc5ODU1MmMwYWFkYTdhMmQ1ZjA2YzhiODJmY2M2OWYyYjdmY2ZmNjRiIiwidGFnIjoiIn0%3D';

    $data = [
        "fingerprint" => [
            "id" => "yYeXFCimkAGnHsmaTWkr",
            "name" => "public.tools.qr-code-generator",
            "locale" => "vi",
            "path" => "qr-code-generator",
            "method" => "GET",
            "v" => "acj"
        ],
        "serverMemo" => [
            "children" => [],
            "errors" => [],
            "htmlHash" => "c4dd7917",
            "data" => [
                "convertType" => "localImage",
                "text" => null,
                "image_size" => 300,
                "custom_logo" => false,
                "remote_url" => null,
                "local_image" => null,
                "logo_size" => 50,
                "data" => [],
                "recaptcha" => null
            ],
            "dataMeta" => [],
            "checksum" => "9c3444541d303e60a22956de2238ea12b31d6dbb1fcf37cf18dd5f57525003b2"
        ],
        "updates" => [
            [
                "type" => "syncInput",
                "payload" => [
                    "id" => "betb",
                    "name" => "text",
                    "value" => $qrData
                ]
            ],
            [
                "type" => "callMethod",
                "payload" => [
                    "id" => "zmhoj",
                    "method" => "onQrCodeGenerator",
                    "params" => []
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode,
            'response' => $response
        ];
    }

    return [
        'success' => true,
        'response' => $response
    ];
}

function downloadQRImage($imageData, $orderId = null)
{
    // Tạo thư mục qr_pay nếu chưa tồn tại
    $uploadDir = __DIR__ . '/../../images/qr_pay/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Tạo tên file
    $fileName = 'qr_' . ($orderId ? $orderId . '_' : '') . time() . '.png';
    $filePath = $uploadDir . $fileName;

    // Decode base64 data
    if (strpos($imageData, 'data:image/png;base64,') === 0) {
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
    }

    $decodedData = base64_decode($imageData);

    if ($decodedData === false) {
        return [
            'success' => false,
            'error' => 'Failed to decode base64 image data'
        ];
    }

    // Lưu file
    $result = file_put_contents($filePath, $decodedData);

    if ($result === false) {
        return [
            'success' => false,
            'error' => 'Failed to save image file'
        ];
    }

    return [
        'success' => true,
        'file_path' => $filePath,
        'file_name' => $fileName,
        'relative_path' => 'qr_pay/' . $fileName
    ];
}

// Xử lý request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ POST request
    $input = json_decode(file_get_contents('php://input'), true);
    $amount = $input['amount'] ?? 0;
    $content = $input['content'] ?? 'Book Store Payment';
    $orderId = $input['order_id'] ?? '';


    // Thêm order ID vào content nếu có
    if ($orderId) {
        $content = "BookStore - Don hang #" . $orderId;
    }

    $result = generateBankQR($amount, $content);

    if ($result['success']) {
        // Kiểm tra response từ VietQR API
        if (isset($result['data']['qrCode']) && isset($result['data']['qrData'])) {
            $qrData = $result['data']['qrData'];

            // Gửi QR data đến json.vn để xử lý
            $jsonVnResult = processQRCodeWithJsonVn($qrData);

            if ($jsonVnResult['success']) {
                // Tìm đường dẫn ảnh trong response
                $response = $jsonVnResult['response'];
                // Cập nhật regex pattern để phù hợp với format thực tế
                preg_match('/src=\\"(data:image\/png;base64,[^"\\\\]+)\\"/', $response, $matches);

                if (isset($matches[1])) {
                    $imageData = $matches[1];

                    // Tải ảnh xuống
                    $downloadResult = downloadQRImage($imageData, $orderId);

                    if ($downloadResult['success']) {
                        echo json_encode([
                            'success' => true,
                            'qr_code' => $result['data']['qrCode'],
                            'qr_data' => $result['data']['qrData'],
                            'local_qr_image' => $downloadResult['relative_path'],
                            'bank_info' => [
                                'bank_name' => 'Vietcombank',
                                'account_number' => '1030382538',
                                'account_name' => 'VU BA NHAT KHANG',
                                'amount' => $amount,
                                'content' => $content
                            ]
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'qr_code' => $result['data']['qrCode'],
                            'qr_data' => $result['data']['qrData'],
                            'bank_info' => [
                                'bank_name' => 'Vietcombank',
                                'account_number' => '1030382538',
                                'account_name' => 'VU BA NHAT KHANG',
                                'amount' => $amount,
                                'content' => $content
                            ],
                            'download_error' => $downloadResult['error']
                        ]);
                    }
                } else {
                    // Thêm debug info để kiểm tra response
                    echo json_encode([
                        'success' => false,
                        'error' => 'Không tìm thấy đường dẫn ảnh trong response từ json.vn',
                        'debug_response' => substr($response, 0, 1000) // Chỉ lấy 1000 ký tự đầu để debug
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => true,
                    'qr_code' => $result['data']['qrCode'],
                    'qr_data' => $result['data']['qrData'],
                    'bank_info' => [
                        'bank_name' => 'Vietcombank',
                        'account_number' => '1030382538',
                        'account_name' => 'VU BA NHAT KHANG',
                        'amount' => $amount,
                        'content' => $content
                    ],
                    'json_vn_error' => $jsonVnResult['error']
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Không thể tạo mã QR',
                'response' => $result['data']
            ]);
        }
    } else {
        echo json_encode($result);
    }
}
// Xử lý GET request để test
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $amount = $_GET['amount'] ?? 1000;
    $content = $_GET['content'] ?? 'Test payment';

    $result = generateBankQR($amount, $content);

    if ($result['success'] && isset($result['data']['qrCode'])) {
        ?>
            <!DOCTYPE html>
            <html lang="vi">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Test VietQR</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>

            <body>
                <div class="container mt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Test VietQR API</h5>
                                </div>
                                <div class="card-body text-center">
                                    <h6>Thông tin chuyển khoản:</h6>
                                    <p><strong>Ngân hàng:</strong> Vietcombank</p>
                                    <p><strong>Số tài khoản:</strong> 1030382538</p>
                                    <p><strong>Chủ tài khoản:</strong> VU BA NHAT KHANG</p>
                                    <p><strong>Số tiền:</strong> <?php echo number_format($amount); ?> VNĐ</p>
                                    <p><strong>Nội dung:</strong> <?php echo htmlspecialchars($content); ?></p>

                                    <div class="mt-4">
                                        <img src="<?php echo $result['data']['qrCode']; ?>" alt="QR Code" class="img-fluid"
                                            style="max-width: 300px;">
                                    </div>

                                    <div class="mt-3">
                                        <small class="text-muted">Quét mã QR để chuyển khoản</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>

            </html>
        <?php
    } else {
        echo json_encode($result);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}


?>