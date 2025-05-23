<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách tòa nhà cho filter
$select_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$apartment_filter = isset($_GET['apartment']) ? mysqli_real_escape_string($conn, $_GET['apartment']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "d.InvoiceCode LIKE '%$search%'";
}
if (!empty($building_filter)) {
    $where_conditions[] = "a.BuildingId = '$building_filter'";
}
if (!empty($apartment_filter)) {
    $where_conditions[] = "d.ApartmentID = '$apartment_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "d.Status = '$status_filter'";
}

// Thêm điều kiện mặc định cho trạng thái Chờ xác nhận
$where_conditions[] = "d.Status = 'Chờ xác nhận'";

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM debtstatements d
    LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID 
    $where_clause
");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách bảng kê
$query = "
    SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, s.Name as StaffName,
           (SELECT SUM(dd.PaidAmount) FROM debtstatementdetail dd WHERE dd.InvoiceCode = d.InvoiceCode) as TotalPaid
    FROM debtstatements d
    LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
    LEFT JOIN staffs s ON d.StaffID = s.ID
    $where_clause
    ORDER BY d.IssueDate DESC
    LIMIT $offset, $records_per_page
";

$select_bills = mysqli_query($conn, $query);

// Lấy danh sách căn hộ cho filter
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code, Name FROM apartment");

// Xử lý duyệt bảng kê
if(isset($_POST['approve_bill'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_POST['invoice_code']);
    
    // Cập nhật trạng thái bảng kê
    $update_query = "UPDATE debtstatements 
                    SET Status = 'Chờ thanh toán',
                        ApprovalDate = NOW()
                    WHERE InvoiceCode = '$invoice_code' 
                    AND Status = 'Chờ xác nhận'";
    
    if(mysqli_query($conn, $update_query)) {
        $_SESSION['success_msg'] = 'Duyệt bảng kê thành công!';
        // Giữ lại các tham số tìm kiếm hiện tại
        $current_url = $_SERVER['REQUEST_URI'];
        header("Location: $current_url");
        exit();
    } else {
        $_SESSION['error_msg'] = 'Có lỗi xảy ra khi duyệt bảng kê! ' . mysqli_error($conn);
        header("Location: $current_url");
        exit();
    }
}

// Hiển thị thông báo từ session
if(isset($_SESSION['success_msg'])) {
    $success_msg[] = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if(isset($_SESSION['error_msg'])) {
    $error_msg[] = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách bảng kê</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card .icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 15px;
        }

        .stats-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .stats-label {
            color: #666;
            margin: 0;
        }

        .stats-link {
            color: #476a52;
            text-decoration: none;
            font-size: 14px;
        }

        .search-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .apartment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .apartment-table th {
            background: #6b8b7b !important;
            color: white;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-occupied {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-renovating {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-empty {
            background: #ffebee;
            color: #c62828;
        }

        .status-away {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            padding: 0;
            background-color: transparent;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #4a5568;
            text-decoration: none;
        }

        .add-btn {
            margin-bottom: 10px;
            width: fit-content;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background-color: #476a52 !important;
            color: white !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .bill-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-paid {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-overdue {
            background: #ffebee;
            color: #c62828;
        }
        .action-buttons button {
            padding: 4px 8px;
            margin: 0 2px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="container-fluid p-4">
                <div class="page-header mb-4">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">
                        DANH SÁCH BẢNG KÊ
                    </h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <span>Danh sách bảng kê</span>
                    </div>
                </div>

                <?php
                if(isset($success_msg)){
                    foreach($success_msg as $msg){
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            '.$msg.'
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                }

                if(isset($error_msg)){
                    foreach($error_msg as $msg){
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            '.$msg.'
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                }
                ?>

                <!-- Search Form -->
                <div class="search-container mb-4">
                    <form class="row g-3">
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="search" placeholder="Mã bảng kê">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="building">
                                <option value="">Chọn tòa nhà</option>
                                <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                    <option value="<?php echo $building['ID']; ?>">
                                        <?php echo $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="apartment">
                                <option value="">Chọn căn hộ</option>
                                <?php while($apt = mysqli_fetch_assoc($select_apartments)) { ?>
                                    <option value="<?php echo $apt['ApartmentID']; ?>">
                                        <?php echo $apt['Code'] . ' - ' . $apt['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="month" class="form-control" name="month">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Bills Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>MÃ BẢNG KÊ</th>
                                <th>KỲ BẢNG KÊ</th>
                                <th>CĂN HỘ</th>
                                <th>HẠN TT</th>
                                <th>NỢ</th>
                                <th>GIẢM GIÁ</th>
                                <th>TỔNG TIỀN</th>
                                <th>ĐÃ THANH TOÁN</th>
                                <th>CÒN NỢ</th>
                                <th>NGÀY LẬP</th>
                                <th>TRẠNG THÁI</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_bills) > 0){
                                $i = $offset + 1;
                                while($bill = mysqli_fetch_assoc($select_bills)){
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $bill['InvoiceCode']; ?></td>
                                <td><?php echo $bill['InvoicePeriod']; ?></td>
                                <td><?php echo $bill['ApartmentName']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($bill['DueDate'])); ?></td>
                                <td><?php echo number_format($bill['OutstandingDebt']); ?></td>
                                <td><?php echo number_format($bill['Discount']); ?></td>
                                <td><?php echo number_format($bill['Total']); ?></td>
                                <td><?php echo number_format($bill['PaidAmount']); ?></td>
                                <td><?php echo number_format($bill['RemainingBalance']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($bill['IssueDate'])); ?></td>
                                <td>
                                    <span class="bill-status <?php 
                                        switch($bill['Status']){
                                            case 'Chờ xác nhận': echo 'status-pending'; break;
                                            case 'Chờ thanh toán': echo 'status-pending'; break;
                                            case 'Đã thanh toán': echo 'status-paid'; break;
                                            case 'Quá hạn': echo 'status-overdue'; break;
                                        }
                                    ?>">
                                        <?php echo $bill['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invoice_code" value="<?php echo $bill['InvoiceCode']; ?>">
                                        <button type="submit" name="approve_bill" 
                                                class="btn btn-sm btn-success" 
                                                onclick="return confirm('Bạn có chắc chắn muốn duyệt bảng kê này?')">
                                            <i class="fas fa-check"></i> Duyệt
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="15" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                    echo !empty($apartment_filter) ? "&apartment=$apartment_filter" : '';
                                    echo !empty($status_filter) ? "&status=$status_filter" : '';
                                ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Hiển thị</span>
                        <select class="form-select" style="width: auto;" onchange="window.location.href=this.value">
                            <?php foreach ([7, 10, 25, 50] as $per_page): ?>
                                <option value="?per_page=<?php echo $per_page; ?>" 
                                        <?php echo ($records_per_page == $per_page) ? 'selected' : ''; ?>>
                                    <?php echo $per_page; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.approve-bill').click(function() {
            const invoiceCode = $(this).data('invoice-code');
            const button = $(this);
            
            if(confirm('Bạn có chắc chắn muốn duyệt bảng kê này?')) {
                $.ajax({
                    url: 'approve_bill.php',
                    method: 'POST',
                    data: {
                        invoice_code: invoiceCode
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if(data.success) {
                            // Cập nhật UI
                            const row = button.closest('tr');
                            row.find('td:nth-last-child(2) span').text('Chờ thanh toán')
                                .removeClass('status-pending status-overdue')
                                .addClass('status-pending');
                            // Ẩn nút duyệt
                            button.remove();
                            // Hiển thị thông báo
                            alert('Duyệt bảng kê thành công!');
                        } else {
                            alert('Có lỗi xảy ra: ' + data.message);
                        }
                    },
                    error: function() {
                        alert('Có lỗi xảy ra khi xử lý yêu cầu!');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>