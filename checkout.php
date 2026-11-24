// Contoh Permintaan API Midtrans di PHP
$serverKey   = 'YOUR_SERVER_KEY';
$orderId     = 'ONLINE-' . date("YmdHis");
$grossAmount = 50000; // Total tagihan transaksi

$payload = [
    'payment_type' => 'qris',
    'transaction_details' => [
        'order_id'     => $orderId,
        'gross_amount' => $grossAmount
    ],
    'qris' => [
        'acquirer' => 'gopay' // Acquirer yang didukung: gopay, airpay shopee, dll.
    ]
];

$ch = curl_init('https://api.sandbox.midtrans.com/v2/charge');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($serverKey . ':')
]);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// Ambil URL Gambar QR Code dari respons API
$qrCodeUrl = $response['actions']['url'] ?? '';