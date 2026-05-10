<?php
session_start();
require_once 'config.php';

// Get Teacher ID from URL (optional, defaults to 0 if just topping up wallet)
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// Default amount can be changed by the user
$default_amount = isset($_GET['price']) ? number_format((float)$_GET['price'], 2, '.', '') : '25.00';

$teacher_name = "";
if ($teacher_id > 0) {
    $stmt = $pdo->prepare("SELECT fullname FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher_name = $stmt->fetchColumn() ?: "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flexible Checkout | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .checkout-card { max-width: 450px; margin: 50px auto; background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); overflow: hidden; }
        .checkout-header { background: #4a90e2; color: white; padding: 30px; text-align: center; }
        
        /* Styled Input for Price */
        .price-input-group { position: relative; max-width: 200px; margin: 15px auto; }
        .price-input-group span { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 1.5rem; font-weight: 800; opacity: 0.8; }
        .amount-input { 
            width: 100%; 
            background: rgba(255,255,255,0.2); 
            border: 2px solid rgba(255,255,255,0.3); 
            border-radius: 15px; 
            padding: 10px 10px 10px 40px; 
            color: white; 
            font-size: 2rem; 
            font-weight: 800; 
            text-align: center;
            outline: none;
            transition: 0.3s;
        }
        .amount-input:focus { background: rgba(255,255,255,0.3); border-color: white; }
        
        .loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); display: none; align-items: center; justify-content: center; z-index: 9999; flex-direction: column; }
    </style>
</head>
<body>

<div id="loader" class="loader-overlay">
    <div class="spinner-border text-primary" role="status"></div>
    <h5 class="mt-3 fw-bold">Processing Payment...</h5>
</div>

<div class="container">
    <div class="checkout-card">
        <div class="checkout-header">
            <h6 class="text-uppercase small fw-bold opacity-75">Enter Amount</h6>
            
            <div class="price-input-group">
                <span>$</span>
                <input type="number" id="customAmount" class="amount-input" value="<?= $default_amount ?>" step="0.01" min="1.00">
            </div>

            <?php if($teacher_name): ?>
                <p class="mb-0 small opacity-90">Paying for lesson with <strong><?= htmlspecialchars($teacher_name) ?></strong></p>
            <?php else: ?>
                <p class="mb-0 small opacity-90">Adding funds to your wallet</p>
            <?php endif; ?>
        </div>

        <div class="p-4">
            <div class="alert alert-info py-2 small border-0 text-center">
                <i class="fas fa-info-circle me-1"></i> You can change the amount above before paying.
            </div>

            <div id="paypal-button-container"></div>
            
            <div class="text-center mt-3">
                <a href="student_dashboard.php" class="text-muted small text-decoration-none">Cancel</a>
            </div>
        </div>
    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD"></script>

<script>
    paypal.Buttons({
        style: { layout: 'vertical', color: 'blue', shape: 'pill', label: 'pay' },

        createOrder: function(data, actions) {
            // Get the current value from the input field right when the button is clicked
            let userAmount = document.getElementById('customAmount').value;
            
            if (userAmount < 1) {
                alert("Please enter a valid amount (minimum $1.00)");
                return;
            }

            return actions.order.create({
                purchase_units: [{
                    description: "<?= $teacher_id > 0 ? 'Teacher Lesson ID: ' . $teacher_id : 'Wallet Deposit' ?>",
                    amount: { value: userAmount }
                }]
            });
        },

        onApprove: function(data, actions) {
            document.getElementById('loader').style.display = 'flex';

            return actions.order.capture().then(function(details) {
                const orderId = details.id;
                const paidAmount = details.purchase_units[0].amount.value;
                const tId = "<?= $teacher_id ?>";

                // Redirect to your processing page
                window.location.href = `process_payment.php?order_id=${orderId}&amount=${paidAmount}&teacher_id=${tId}`;
            });
        }
    }).render('#paypal-button-container');
</script>

</body>
</html>