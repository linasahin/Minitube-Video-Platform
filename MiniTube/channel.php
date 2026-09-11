<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: channel.php
    Description: Shows full channel details (name, image, category, owner name,
                 owner country, creation date, subscriber count, description).
                 Lists all channel videos with title, duration, upload date, view count.
                 Includes Subscribe/Unsubscribe toggle button.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";
$dbname     = "lina_sahin";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id    = isset($_GET['user_id'])    ? intval($_GET['user_id'])    : 0;
$channel_id = isset($_GET['channel_id']) ? intval($_GET['channel_id']) : 0;

// Handle Subscribe / Unsubscribe toggle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_sub'])) {
    $check = $conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $user_id AND channel_id = $channel_id");
    if ($check->num_rows > 0) {
        // Already subscribed → unsubscribe
        $conn->query("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = $user_id AND channel_id = $channel_id");
    } else {
        // Not subscribed → subscribe
        $today = date('Y-m-d');
        $conn->query("INSERT INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at) VALUES ($user_id, $channel_id, '$today')");
    }
    // Reload page so button and count reflect the new state
    header("Location: channel.php?user_id=$user_id&channel_id=$channel_id");
    exit();
}

// Fetch channel details + owner info (JOIN USERS to get owner's full_name and country)
$channel_sql = "
    SELECT C.*, U.full_name AS owner_name, U.country AS owner_country
    FROM CHANNELS C
    JOIN USERS U ON C.owner_id = U.user_id
    WHERE C.channel_id = $channel_id
";
$channel_res = $conn->query($channel_sql);
$channel     = $channel_res ? $channel_res->fetch_assoc() : null;
if (!$channel) { die("Channel not found."); }

// Live subscriber count
$sub_count_res = $conn->query("SELECT COUNT(*) AS total FROM SUBSCRIPTIONS WHERE channel_id = $channel_id");
$sub_count     = $sub_count_res->fetch_assoc()['total'];

// Check if the current user is already subscribed
$is_subbed_res = $conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $user_id AND channel_id = $channel_id");
$is_subbed     = $is_subbed_res->num_rows > 0;

// All videos for this channel: title, duration, upload date, view count
$videos_result = $conn->query("
    SELECT video_id, title, duration_seconds, uploaded_at, view_count
    FROM VIDEOS
    WHERE channel_id = $channel_id
    ORDER BY uploaded_at DESC
");

// Helper: format seconds as M:SS
function format_duration($seconds) {
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($channel['name']); ?> - MiniTube</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
        header {
            background: #fff;
            padding: 12px 30px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        header a { text-decoration: none; color: #6c757d; font-size: 14px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 25px 30px; }

        /* Channel header card */
        .channel-card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            padding: 25px;
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }
        .channel-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .channel-info { flex: 1; }
        .channel-info h1 { margin: 0 0 8px 0; font-size: 22px; color: #212529; }
        .info-row { font-size: 13px; color: #6c757d; margin: 4px 0; }
        .info-row strong { color: #495057; }
        .description {
            margin-top: 10px;
            font-size: 14px;
            color: #495057;
            font-style: italic;
        }

        /* Subscribe button */
        .btn-sub {
            padding: 10px 22px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-sub.subscribe   { background: #dc3545; color: #fff; }
        .btn-sub.unsubscribe { background: #6c757d; color: #fff; }
        .btn-sub:hover { opacity: 0.88; }

        /* Videos table */
        .videos-section { margin-top: 30px; }
        .videos-section h3 { margin: 0 0 15px 0; color: #212529; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        th {
            background: #f1f3f5;
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            color: #495057;
        }
        td {
            padding: 12px 15px;
            font-size: 14px;
            color: #212529;
            border-top: 1px solid #f1f3f5;
        }
        td a { text-decoration: none; color: #007bff; }
        td a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <a href="feed.php?user_id=<?php echo $user_id; ?>">← Back to Home Feed</a>
    <span style="font-weight:600; color:#dc3545;">📺 MiniTube</span>
</header>

<div class="container">

    <!-- Channel header card -->
    <div class="channel-card">
        <!-- Spec: show channel_image -->
        <img src="<?php echo htmlspecialchars($channel['channel_image']); ?>"
             alt="<?php echo htmlspecialchars($channel['name']); ?> logo"
             onerror="this.src='https://via.placeholder.com/100'">

        <div class="channel-info">
            <h1><?php echo htmlspecialchars($channel['name']); ?></h1>
            <div class="info-row"><strong>Category:</strong> <?php echo htmlspecialchars($channel['category']); ?></div>
            <!-- Spec: show owner's full name and country -->
            <div class="info-row"><strong>Owner:</strong> <?php echo htmlspecialchars($channel['owner_name']); ?></div>
            <div class="info-row"><strong>Owner Country:</strong> <?php echo htmlspecialchars($channel['owner_country']); ?></div>
            <!-- Spec: show creation date -->
            <div class="info-row"><strong>Created:</strong> <?php echo htmlspecialchars($channel['created_on']); ?></div>
            <div class="info-row"><strong>Subscribers:</strong> <strong><?php echo number_format($sub_count); ?></strong></div>
            <!-- Spec: display "(no description)" when description is empty -->
            <div class="description">
                <?php echo !empty($channel['description']) ? htmlspecialchars($channel['description']) : '(no description)'; ?>
            </div>
        </div>

        <!-- Spec: button text must be "Subscribe" or "Unsubscribe" -->
        <form method="POST">
            <button type="submit" name="toggle_sub"
                    class="btn-sub <?php echo $is_subbed ? 'unsubscribe' : 'subscribe'; ?>">
                <?php echo $is_subbed ? 'Unsubscribe' : 'Subscribe'; ?>
            </button>
        </form>
    </div>

    <!-- Videos list -->
    <div class="videos-section">
        <h3>Videos (<?php echo $videos_result ? $videos_result->num_rows : 0; ?>)</h3>
        <?php if ($videos_result && $videos_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Upload Date</th>
                        <th>Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($vid = $videos_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $vid['video_id']; ?>">
                                    <?php echo htmlspecialchars($vid['title']); ?>
                                </a>
                            </td>
                            <!-- Spec: show formatted duration -->
                            <td><?php echo format_duration($vid['duration_seconds']); ?></td>
                            <td><?php echo htmlspecialchars($vid['uploaded_at']); ?></td>
                            <td><?php echo number_format($vid['view_count']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#6c757d;">This channel has not uploaded any videos yet.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
<?php $conn->close(); ?>
