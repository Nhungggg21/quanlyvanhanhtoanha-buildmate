<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Demo Chữ ký số - Nhập tay & Lưu DB</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 20px;
        }

        .form-container {
            max-width: 500px;
            margin: 0 auto;
        }

        canvas {
            border: 1px solid #000;
            margin: 20px 0;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
        }

        label {
            display: block;
            text-align: left;
            margin-top: 10px;
        }

        button,
        input[type="submit"] {
            padding: 10px 20px;
            margin: 5px;
            cursor: pointer;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <h2>Demo: Ký hợp đồng mua căn hộ</h2>
    <div class="form-container">
        <form id="contractForm" method="POST" action="sign_contract.php">
            <h3>Vẽ chữ ký của bạn</h3>
            <canvas id="signatureCanvas" width="400" height="200"></canvas>
            <button type="button" id="clearBtn">Xóa chữ ký</button>
            <input type="hidden" name="signature" id="signatureData">
            <input type="submit" value="Ký và lưu hợp đồng">
        </form>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasDrawn = false; // Biến để theo dõi xem đã vẽ hay chưa

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            drawing = true;
            hasDrawn = true; // Đánh dấu đã vẽ
            const {
                x,
                y
            } = getCoordinates(e);
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function draw(e) {
            if (!drawing) return;
            const {
                x,
                y
            } = getCoordinates(e);
            ctx.lineTo(x, y);
            ctx.stroke();
        }

        function stopDrawing() {
            drawing = false;
        }

        function getCoordinates(e) {
            const rect = canvas.getBoundingClientRect();
            if (e.type.includes('touch')) {
                return {
                    x: e.touches[0].clientX - rect.left,
                    y: e.touches[0].clientY - rect.top
                };
            }
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        }

        document.getElementById('clearBtn').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('signatureData').value = '';
            hasDrawn = false; // Đặt lại trạng thái
        });

        document.getElementById('contractForm').addEventListener('submit', (e) => {
            if (!hasDrawn) {
                e.preventDefault();
                alert('Vui lòng vẽ chữ ký trước khi gửi!');
            } else {
                const dataUrl = canvas.toDataURL('image/png');
                document.getElementById('signatureData').value = dataUrl;
            }
        });
    </script>
</body>

</html>