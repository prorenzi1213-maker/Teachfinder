<?php
session_start();
require_once 'connections.php'; 

if (!isset($pdo)) {
    die("Database connection variable \$pdo not found.");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$role = $_SESSION['role'] ?? 'student'; // Get role to redirect back correctly

// Helper function to make links clickable
function linkify($text) {
    $url = '@(http)?(s)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]|[^\s\.;.]|[^\s\b.,?:]){2,}|localhost(:[0-9]+)?|(([0-9]{1,3}\.){3}[0-9]{1,3}))(/[^\s]*)?@';
    return preg_replace($url, '<a href="$0" target="_blank" style="color: inherit; text-decoration: underline;">$0</a>', htmlspecialchars($text));
}

// 1. IMPROVED SIDEBAR QUERY
try {
    $contact_stmt = $pdo->prepare("
        SELECT u.id, u.username, t.profile_pic,
        (SELECT message_text FROM messages 
         WHERE (sender_id = u.id AND receiver_id = :uid) 
            OR (sender_id = :uid AND receiver_id = u.id) 
         ORDER BY sent_at DESC LIMIT 1) as last_msg
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        WHERE u.id IN (
            SELECT DISTINCT CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END
            FROM messages WHERE sender_id = :uid OR receiver_id = :uid
        ) OR u.id = :rid
    ");
    $contact_stmt->execute(['uid' => $user_id, 'rid' => $receiver_id]);
    $contacts = $contact_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $contacts = []; }

// 2. ACTIVE USER DETAILS
$active_user = null;
if ($receiver_id > 0) {
    $t_stmt = $pdo->prepare("SELECT u.username, t.profile_pic FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.id = ?");
    $t_stmt->execute([$receiver_id]);
    $active_user = $t_stmt->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$receiver_id, $user_id]);
}

// 3. SEND MESSAGE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['message_text'] ?? '')) && $receiver_id > 0) {
    $msg = trim($_POST['message_text']);
    $send_stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
    if($send_stmt->execute([$user_id, $receiver_id, $msg])) {
        header("Location: message.php?receiver_id=$receiver_id");
        exit();
    }
}

// 4. FETCH CHAT HISTORY
$messages = [];
if ($receiver_id > 0) {
    $m_stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY sent_at ASC");
    $m_stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
    $messages = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messenger | TeachFinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; height: 100vh; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        .messenger-container { display: flex; height: 100vh; width: 100%; background: #fff; }
        
        /* Sidebar Styles */
        .sidebar-contacts { width: 360px; border-right: 1px solid #eee; display: flex; flex-direction: column; background: #fff; }
        .sidebar-header { padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f8f9fa; }
        .sidebar-header h4 { margin: 0; font-weight: 800; font-size: 1.4rem; }
        .contact-list { overflow-y: auto; flex: 1; padding-top: 10px; }
        
        .contact-item { display: flex; align-items: center; padding: 12px; text-decoration: none; color: inherit; margin: 0 10px; border-radius: 10px; transition: 0.2s; }
        .contact-item:hover { background: #f5f5f5; }
        .contact-item.active { background: #e7f3ff; color: #0084ff; }
        .contact-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 12px; }

        /* Chat Window Styles */
        .chat-window { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; align-items: center; background: #fff; }
        .chat-body { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 8px; background: #fff; }
        
        .msg-bubble { max-width: 65%; padding: 10px 16px; border-radius: 18px; font-size: 0.95rem; word-wrap: break-word; }
        .sent { align-self: flex-end; background: #0084ff; color: white; border-bottom-right-radius: 4px; }
        .received { align-self: flex-start; background: #f1f5f9; color: #050505; border-bottom-left-radius: 4px; }
        
        .chat-footer { padding: 20px; border-top: 1px solid #eee; background: #fff; }
        .input-bar { background: #f0f2f5; border-radius: 25px; border: none; padding: 12px 20px; width: 100%; outline: none; }
        
        .btn-back { font-size: 0.9rem; text-decoration: none; color: #65676b; transition: 0.2s; }
        .btn-back:hover { color: #0084ff; }
    </style>
</head>
<body>

<div class="messenger-container">
    <aside class="sidebar-contacts">
        <div class="sidebar-header">
            <h4>Chats</h4>
            <a href="<?= $role ?>_dashboard.php" class="btn-back">
                <i class="fas fa-th-large me-1"></i> Dashboard
            </a>
        </div>
        
        <div class="contact-list">
            <?php foreach ($contacts as $c): ?>
                <?php if($c['id'] == $user_id) continue; ?>
                <a href="message.php?receiver_id=<?= $c['id'] ?>" class="contact-item <?= $receiver_id == $c['id'] ? 'active' : '' ?>">
                    <img src="uploads/<?= !empty($c['profile_pic']) ? $c['profile_pic'] : 'default.jpg' ?>" class="contact-img">
                    <div class="overflow-hidden">
                        <div class="fw-bold"><?= htmlspecialchars($c['username']) ?></div>
                        <div class="small text-muted text-nowrap"><?= htmlspecialchars($c['last_msg'] ?? 'Start a conversation...') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="chat-window">
        <?php if ($active_user): ?>
            <header class="chat-header">
                <img src="uploads/<?= !empty($active_user['profile_pic']) ? $active_user['profile_pic'] : 'default.jpg' ?>" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover;">
                <div class="fw-bold"><?= htmlspecialchars($active_user['username']) ?></div>
            </header>

            <div class="chat-body" id="chatBox">
                <?php foreach ($messages as $m): ?>
                    <div class="msg-bubble <?= $m['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <?= linkify($m['message_text']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <footer class="chat-footer">
                <form method="POST" class="d-flex gap-2">
                    <input type="text" name="message_text" class="input-bar" placeholder="Type a message..." required autocomplete="off">
                    <button type="submit" class="btn btn-link text-primary p-0"><i class="fas fa-paper-plane fa-lg"></i></button>
                </form>
            </footer>
        <?php else: ?>
            <div class="m-auto text-center text-muted">
                <i class="fab fa-facebook-messenger fa-4x mb-3 opacity-25"></i>
                <h5>Select a chat to see messages</h5>
                <p class="small">Click on a person in the sidebar to start.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    // Auto scroll to bottom
    const box = document.getElementById('chatBox');
    if(box) box.scrollTop = box.scrollHeight;

    // Stop form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
</body>
</html>