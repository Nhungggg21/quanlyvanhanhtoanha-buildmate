<?php
    // Bật hiển thị lỗi
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Ghi log để kiểm tra
    $logMessage = "sign_contract.php được gọi: " . date('Y-m-d H:i:s') . "\n";
    if (!file_put_contents(__DIR__ . '/debug.txt', $logMessage, FILE_APPEND)) {
        die("Không thể ghi file debug.txt. Kiểm tra quyền ghi file!");
    }

    // Kiểm tra tài khoản PHP
    $user = exec('whoami');
    file_put_contents(__DIR__ . '/debug.txt', "PHP chạy dưới tài khoản: $user\n", FILE_APPEND);

    // Kiểm tra phương thức POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $error = "Yêu cầu không hợp lệ!";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Kết nối MySQL
    $conn = new mysqli('localhost', 'root', '', 'webtoanha');
    if ($conn->connect_error) {
        $error = "Kết nối database thất bại: " . $conn->connect_error;
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Include TCPDF
    require_once 'tcpdf/tcpdf.php';

    // Kiểm tra quyền ghi
    $contractsDir = __DIR__ . '/contracts';
    if (!is_dir($contractsDir)) {
        if (!mkdir($contractsDir, 0777, true)) {
            $error = "Không thể tạo thư mục contracts!";
            file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
            die($error);
        }
    }

    $signaturesDir = __DIR__ . '/signatures'; // Thư mục lưu chữ ký cố định
    if (!is_dir($signaturesDir)) {
        if (!mkdir($signaturesDir, 0777, true)) {
            $error = "Không thể tạo thư mục signatures!";
            file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
            die($error);
        }
    }

    $testFile = __DIR__ . '/contracts/test.txt';
    if (file_put_contents($testFile, 'Test') === false) {
        $error = "PHP không có quyền ghi vào thư mục contracts! Lỗi: " . error_get_last()['message'];
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    } else {
        file_put_contents(__DIR__ . '/debug.txt', "PHP có quyền ghi vào thư mục contracts.\n", FILE_APPEND);
        unlink($testFile);
    }

    // Lấy dữ liệu từ form
    $projectId = $_POST['project_id'] ?? '';
    $representative = $_POST['representative'] ?? '';
    $position = $_POST['position'] ?? '';
    $address = $_POST['address'] ?? '';
    $residentId = $_POST['resident_id'] ?? '';
    $nationalId = $_POST['national_id'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $contractCode = $_POST['contract_code'] ?? '';
    $creationDate = $_POST['creation_date'] ?? '';
    $cretionDate = $_POST['cretion_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $buildingId = $_POST['building_id'] ?? '';
    $apartmentId = $_POST['apartment_id'] ?? '';
    $area = $_POST['area'] ?? '';
    $signatureDataA = $_POST['signature_a'] ?? '';
    $signatureDataB = $_POST['signature_b'] ?? '';
    $services = $_POST['services'] ?? []; // Lấy dữ liệu dịch vụ

    file_put_contents(__DIR__ . '/debug.txt', "Dữ liệu POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

    // Kiểm tra dữ liệu
    if (empty($projectId) || empty($residentId) || empty($nationalId) || empty($contractCode) || empty($cretionDate) || empty($buildingId) || empty($apartmentId) || empty($signatureDataA) || empty($signatureDataB)) {
        $error = "Vui lòng điền đầy đủ thông tin và vẽ cả hai chữ ký!";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Thông tin bổ sung
    $contractDate = date('Y-m-d'); // Định dạng cho MySQL

    // Lưu chữ ký cố định
    // Chữ ký Bên A (dựa trên project_id)
    $signatureBase64A = preg_replace('#^data:image/\w+;base64,#i', '', $signatureDataA);
    $signatureImageA = base64_decode($signatureBase64A);
    $sigFileA = __DIR__ . '/signatures/signature_a_' . $projectId . '.png'; // Lưu cố định với tên dựa trên project_id
    file_put_contents($sigFileA, $signatureImageA);
    file_put_contents(__DIR__ . '/debug.txt', "Lưu chữ ký Bên A: $sigFileA\n", FILE_APPEND);

    // Chữ ký Bên B (dựa trên resident_id)
    $signatureBase64B = preg_replace('#^data:image/\w+;base64,#i', '', $signatureDataB);
    $signatureImageB = base64_decode($signatureBase64B);
    $sigFileB = __DIR__ . '/signatures/signature_b_' . $residentId . '.png'; // Lưu cố định với tên dựa trên resident_id
    file_put_contents($sigFileB, $signatureImageB);
    file_put_contents(__DIR__ . '/debug.txt', "Lưu chữ ký Bên B: $sigFileB\n", FILE_APPEND);

    // Tạo file PDF hợp đồng
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Thiết lập thông tin tài liệu
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Quản lý tòa nhà');
    $pdf->SetTitle('Hợp đồng quản lý vận hành nhà chung cư');
    $pdf->SetSubject('Hợp đồng');
    $pdf->SetKeywords('Hợp đồng, quản lý, căn hộ');

    // Thiết lập lề
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);

    // Thiết lập font chữ hỗ trợ tiếng Việt
    $pdf->SetFont('dejavusans', '', 12);

    // Tắt header và footer mặc định
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Thêm trang
    $pdf->AddPage();

    // Tiêu đề chính
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 8, "CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM", 0, 1, 'C');
    $pdf->Cell(0, 8, "Độc lập - Tự do - Hạnh phúc", 0, 1, 'C');
    $pdf->Cell(0, 8, "----------------", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, "HỢP ĐỒNG DỊCH VỤ QUẢN LÝ VẬN HÀNH NHÀ CHUNG CƯ", 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->Cell(0, 8, "Căn cứ Bộ Luật Dân sự số 91/2015/QH13:", 0, 1, 'L');
    $pdf->Cell(0, 8, "Căn cứ Luật Xây dựng số 50/2014/QH13:", 0, 1, 'L');
    $pdf->Cell(0, 8, "Căn cứ Nghị định số 99/2015/NĐ-CP ngày 20 tháng 10 năm 2015 của Chính phủ ", 0, 1, 'L');
    $pdf->Cell(0, 8, "quy định chi tiết và hướng dẫn thi hành một số điều của Luật Nhà ở:", 0, 1, 'L');
    $pdf->Cell(0, 8, "Căn cứ Thông tư số 02/2016/TT-BXD ngày 15 tháng 02 năm 2016", 0, 1, 'L');
    $pdf->Cell(0, 8, "của Bộ Xây dựng ban hành quy chế quản lý, sử dụng nhà chung cư:", 0, 1, 'L');
    $pdf->Cell(0, 8, "Căn cứ các quy định pháp luật hiện hành khác có liên quan,", 0, 1, 'L');
    $pdf->Cell(0, 8, "và sự thỏa thuận giữa hai bên,", 0, 1, 'L');

    $pdf->Ln(5);

    // Hai bên tham gia ký kết hợp đồng
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "Hai bên tham gia ký kết hợp đồng được bảo gồm:", 0, 1, 'L');
    $pdf->Ln(5);

    // Phần BÊN A
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "BÊN A: Đơn vị quản lý vận hành nhà chung cư (sau đây gọi tắt là Bên A)", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $partyA = [
        "Tên đơn vị quản lý: $projectId",
        "Người đại diện: $representative",
        "Chức vụ: $position",
        "Địa chỉ: $address",
        "Điện thoại: 024 3755 1919 - Dịch vụ khách hàng: 024 3755 1919"
    ];
    foreach ($partyA as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Phần BÊN B
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "BÊN B: Chủ sở hữu căn hộ (sau đây gọi tắt là Bên B)", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $partyB = [
        "Họ và tên: $residentId",
        "Số CCCD: $nationalId",
        "Email: $email",
        "Điện thoại: $phone"
    ];
    foreach ($partyB as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Điều 1: Ràng buộc của nhà chung cư
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "ĐIỀU 1. Ràng buộc của nhà chung cư", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $contractInfo = [
        "1. Tên nhà chung cư: Toà Nhà THK01",
        "2. Mã căn hộ: A003",
        "3. Diện tích: 180 m2"
    ];
    foreach ($contractInfo as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Điều 2: Thông tin nhà chung cư
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "ĐIỀU 2. Thông tin nhà chung cư", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $apartmentInfo = [
        "1. Tòa nhà: $buildingId",
        "2. Căn hộ: $apartmentId",
        "3. Diện tích: $area m²"
    ];
    foreach ($apartmentInfo as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Điều 3: Dịch vụ quản lý vận hành nhà chung cư
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "ĐIỀU 3. Dịch vụ quản lý vận hành nhà chung cư", 0, 1, 'L');
    if (!empty($services)) {
        // Thiết lập bảng
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(200, 220, 255); // Màu nền cho header
        $pdf->Cell(10, 8, "STT", 1, 0, 'C', 1);
        $pdf->Cell(60, 8, "Tên dịch vụ", 1, 0, 'C', 1);
        $pdf->Cell(30, 8, "Giá", 1, 0, 'C', 1);
        $pdf->Cell(30, 8, "Đơn vị tính", 1, 1, 'C', 1);

        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetFillColor(255, 255, 255); // Màu nền cho các hàng
        $index = 1;
        foreach ($services as $service) {
            $serviceName = $service['name'] ?? 'N/A';
            $price = $service['price'] ?? 'N/A';
            $typeOfFee = $service['type_of_fee'] == 'monthly' ? 'Tháng' : 'Một lần';

            $pdf->Cell(10, 8, $index++, 1, 0, 'C');
            $pdf->Cell(60, 8, $serviceName, 1, 0, 'L');
            $pdf->Cell(30, 8, number_format($price, 0, ',', '.') . " VNĐ", 1, 0, 'R');
            $pdf->Cell(30, 8, $typeOfFee, 1, 1, 'C');
        }
    } else {
        $pdf->SetFont('dejavusans', 'I', 10);
        $pdf->Cell(0, 8, "Không có dịch vụ nào được áp dụng.", 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Điều 4: Thời hạn thực hiện hợp đồng
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "ĐIỀU 4. Thời hạn thực hiện hợp đồng", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $timeInfo = [
        "Ngày áp dụng: " . date('d/m/Y', strtotime($cretionDate)),
        "Ngày kết thúc: " . ($endDate ? date('d/m/Y', strtotime($endDate)) : '08/10/2025')
    ];
    foreach ($timeInfo as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Điều 5: Thông tin khác
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, "ĐIỀU 5. Thông tin khác", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $additionalInfo = [
        "Nội dung thực hiện hợp đồng",
        "Ngày áp dụng: 08/04/2025",
        "Ngày kết thúc: 08/10/2025"
    ];
    foreach ($additionalInfo as $line) {
        $pdf->Cell(0, 8, $line, 0, 1, 'L');
    }
    $pdf->Ln(10);

    // Chèn chữ ký
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(90, 8, "BÊN A", 0, 0, 'C');
    $pdf->Cell(90, 8, "BÊN B", 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(90, 8, "(Đại diện hợp tác, chủ nhiệm và sử dụng dịch vụ)", 0, 0, 'C');
    $pdf->Cell(90, 8, "(Ký, ghi rõ họ tên)", 0, 1, 'C');
    $pdf->Ln(5);

    // Chèn hình ảnh chữ ký
    file_put_contents(__DIR__ . '/debug.txt', "Chèn chữ ký Bên A và Bên B\n", FILE_APPEND);
    $pdf->Image($sigFileA, 15, $pdf->GetY(), 50, 25);
    $pdf->Image($sigFileB, 105, $pdf->GetY(), 50, 25);

    // Lưu file PDF
    file_put_contents(__DIR__ . '/debug.txt', "Lưu file PDF\n", FILE_APPEND);
    $contractFile = __DIR__ . '/contracts/contract_' . time() . '.pdf';
    if (!is_dir(__DIR__ . '/contracts')) {
        mkdir(__DIR__ . '/contracts', 0777, true);
    }
    if (!is_writable(__DIR__ . '/contracts')) {
        $error = "Thư mục contracts không có quyền ghi!";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }
    $pdf->Output($contractFile, 'F');
    file_put_contents(__DIR__ . '/debug.txt', "File PDF đã lưu: $contractFile\n", FILE_APPEND);

    // Ký số bằng OpenSSL
    file_put_contents(__DIR__ . '/debug.txt', "Bắt đầu ký số\n", FILE_APPEND);
    $privateKeyPath = __DIR__ . '/certificates/private_key.pem';
    $certPath = __DIR__ . '/certificates/certificate.crt';

    if (!file_exists($privateKeyPath) || !file_exists($certPath)) {
        $error = "Không tìm thấy file chứng chỉ số demo!";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Đọc nội dung private key từ file
    $privateKeyString = file_get_contents($privateKeyPath);
    $privateKey = openssl_pkey_get_private($privateKeyString);

    if (!$privateKey) {
        $error = "Không thể đọc được private key. Kiểm tra lại file PEM hoặc passphrase.";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Đọc nội dung chứng chỉ và dữ liệu cần ký
    $cert = file_get_contents($certPath);
    $dataToSign = file_get_contents($contractFile);

    // Tiến hành ký số
    $signature = '';
    $signaturePath = $contractFile . '.sig';
    if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        $error = "Lỗi khi ký số hợp đồng demo: " . openssl_error_string();
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    if (!file_put_contents($signaturePath, $signature)) {
        $error = "Không thể lưu file chữ ký số: $signaturePath. Kiểm tra quyền ghi file!";
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }
    file_put_contents(__DIR__ . '/debug.txt', "File chữ ký số đã lưu: $signaturePath\n", FILE_APPEND);

    // Lưu vào database
    file_put_contents(__DIR__ . '/debug.txt', "Lưu vào database\n", FILE_APPEND);
    // Chuẩn hóa đường dẫn file
    $contractFile = str_replace('\\', '/', $contractFile);
    $sigFileA = str_replace('\\', '/', $sigFileA);
    $sigFileB = str_replace('\\', '/', $sigFileB);

    // Kiểm tra giá trị trước khi bind_param
    file_put_contents(__DIR__ . '/debug.txt', "Giá trị trước khi bind_param:\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug.txt', "ls_signature: Yes\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug.txt', "contractFile: $contractFile\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug.txt', "sigFileA: $sigFileA\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug.txt', "sigFileB: $sigFileB\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug.txt', "contractCode: $contractCode\n", FILE_APPEND);

    // Lưu vào database
    file_put_contents(__DIR__ . '/debug.txt', "Lưu vào database\n", FILE_APPEND);
    $updateSql = "UPDATE contracts SET 
        ls_signature = 'Yes', 
        status = 'active',
        file = '" . $conn->real_escape_string($contractFile) . "',
        contract_path = '" . $conn->real_escape_string($contractFile) . "', 
        signature_a_path = '" . $conn->real_escape_string($sigFileA) . "', 
        signature_b_path = '" . $conn->real_escape_string($sigFileB) . "' 
        WHERE ContractCode = '" . $conn->real_escape_string($contractCode) . "';";

    if ($conn->query($updateSql) === TRUE) {
        $affectedRows = $conn->affected_rows;
        if ($affectedRows > 0) {
            echo "Hợp đồng demo đã được ký số và lưu vào database thành công!<br>";
            echo "<a href='$contractFile' download>Tải hợp đồng</a>";
            file_put_contents(__DIR__ . '/debug.txt', "Lưu vào database thành công, số hàng bị ảnh hưởng: " . $affectedRows . "\n", FILE_APPEND);
        } else {
            $error = "Không có bản ghi nào được cập nhật. Kiểm tra ContractCode: $contractCode";
            file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
            die($error);
        }
    } else {
        $error = "Lỗi khi lưu vào database: " . $conn->error;
        file_put_contents(__DIR__ . '/debug.txt', $error . "\n", FILE_APPEND);
        die($error);
    }

    // Đóng kết nối
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
?>