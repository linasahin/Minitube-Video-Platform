<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: feed.php
    Description: Homepage shown after login. Left side shows latest videos from
                 subscribed channels (with title, channel name, uploader country,
                 days ago). Right side shows top 5 channels by subscriber count
                 and the logged-in user's profile.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";
$dbname     = "lina_sahin";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

$user_query = $conn->query("SELECT * FROM USERS WHERE user_id = $user_id");
$user_data  = $user_query->fetch_assoc();
if (!$user_data) {
    die("Access Denied: Invalid user session.");
}

$sub_videos_sql = "
    SELECT V.video_id, V.title, V.channel_id, V.uploaded_at, V.view_count,
           C.name AS channel_name,
           C.channel_image AS channel_image,
           U.country AS uploader_country,
           DATEDIFF(NOW(), V.uploaded_at) AS days_ago
    FROM VIDEOS V
    JOIN CHANNELS C     ON V.channel_id   = C.channel_id
    JOIN USERS U        ON C.owner_id     = U.user_id
    JOIN SUBSCRIPTIONS S ON C.channel_id  = S.channel_id
    WHERE S.subscriber_id = $user_id
    ORDER BY V.uploaded_at DESC
";
$sub_videos_result = $conn->query($sub_videos_sql);

$top_channels_sql = "
    SELECT C.channel_id, C.name, C.channel_image, C.category,
           COUNT(S.subscription_id) AS sub_count
    FROM CHANNELS C
    LEFT JOIN SUBSCRIPTIONS S ON C.channel_id = S.channel_id
    GROUP BY C.channel_id
    ORDER BY sub_count DESC
    LIMIT 5
";
$top_channels_result = $conn->query($top_channels_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello, <?php echo htmlspecialchars($user_data['full_name']); ?>!</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        header {
            background: #ffffff;
            padding: 15px 30px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h2 { margin: 0; color: #dc3545; font-size: 22px; }
        .nav-links a {
            margin-left: 15px;
            text-decoration: none;
            color: #495057;
            font-size: 14px;
        }
        .page-body {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 25px;
            padding: 25px 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .feed-section h3 { margin-top: 0; color: #212529; }
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 18px;
        }
        .video-card {
            background: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        .video-info { padding: 14px; }
        .video-title a {
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            color: #212529;
        }
        .video-title a:hover { color: #007bff; }
        .video-channel a {
            font-size: 13px;
            text-decoration: none;
            color: #6c757d;
        }
        .video-meta { font-size: 12px; color: #868e96; margin-top: 6px; }
        .sidebar { display: flex; flex-direction: column; gap: 20px; }
        .sidebar-box {
            background: #ffffff;
            padding: 18px;
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        .sidebar-box h3 {
            margin: 0 0 14px 0;
            font-size: 15px;
            border-bottom: 2px solid #f1f3f5;
            padding-bottom: 10px;
            color: #212529;
        }
        .channel-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
            font-size: 13px;
        }
        .channel-item:last-child { border: none; }
        .channel-item a { text-decoration: none; color: #007bff; font-weight: 500; }
        .sub-badge {
            background: #f1f3f5;
            color: #495057;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 12px;
        }
        .profile-row { font-size: 13px; color: #495057; margin: 6px 0; }
        .profile-row strong { color: #212529; }
        .empty-msg { color: #6c757d; font-size: 14px; padding: 10px 0; }
    </style>
</head>
<body>

<header>
    <h2>📺 MiniTube</h2>
    <div class="nav-links">
        <a href="feed.php?user_id=<?php echo $user_id; ?>">Home</a>
        <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Console</a>
    </div>
</header>

<div class="page-body">

    <div class="feed-section">
        <h3>Latest from Your Subscriptions</h3>
        <div class="video-grid">
            <?php if ($sub_videos_result && $sub_videos_result->num_rows > 0): ?>
                <?php while ($row = $sub_videos_result->fetch_assoc()): ?>
                    <div class="video-card">
                        <div class="video-info">
                            <div class="video-title">
                                <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $row['video_id']; ?>">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                            </div>
                            <div class="video-channel" style="margin-top:6px;">
                                <!-- Spec: channel name AND image must both be links -->
                                <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $row['channel_id']; ?>">
                                    <img src="<?php echo htmlspecialchars($row['channel_image']); ?>"
                                         style="width:20px; height:20px; border-radius:50%; vertical-align:middle; margin-right:5px;"
                                         onerror="this.style.display='none'">
                                    <?php echo htmlspecialchars($row['channel_name']); ?>
                                </a>
                            </div>
                            <div class="video-meta">
                                🌍 <?php echo htmlspecialchars($row['uploader_country']); ?> &nbsp;•&nbsp;
                                <?php
                                    $days = intval($row['days_ago']);
                                    echo $days === 0 ? 'Today' : ($days === 1 ? '1 day ago' : "$days days ago");
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-msg">No videos from subscribed channels yet. Try subscribing to some channels!</p>
            <?php endif; ?>
        </div>
    </div>

    <aside class="sidebar">
        <div class="sidebar-box">
            <h3>🏆 Top 5 Channels</h3>
            <?php if ($top_channels_result && $top_channels_result->num_rows > 0): ?>
                <?php $rank = 1; while ($ch = $top_channels_result->fetch_assoc()): ?>
                    <div class="channel-item">
                        <span>
                            <strong><?php echo $rank++; ?>.</strong>
                            <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $ch['channel_id']; ?>">
                                <?php echo htmlspecialchars($ch['name']); ?>
                            </a>
                        </span>
                        <span class="sub-badge"><?php echo number_format($ch['sub_count']); ?> subs</span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-msg">No channels found.</p>
            <?php endif; ?>
        </div>

        <div class="sidebar-box">
            <h3>👤 Your Profile</h3>
            <div class="profile-row"><strong>Name:</strong> <?php echo htmlspecialchars($user_data['full_name']); ?></div>
            <div class="profile-row"><strong>Username:</strong> @<?php echo htmlspecialchars($user_data['username']); ?></div>
            <div class="profile-row"><strong>Country:</strong> <?php echo htmlspecialchars($user_data['country']); ?></div>
            <div class="profile-row"><strong>Joined:</strong> <?php echo htmlspecialchars($user_data['joined_on']); ?></div>
            <div class="profile-row"><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></div>
            <?php if (!empty($user_data['bio'])): ?>
                <div class="profile-row" style="margin-top:8px; font-style:italic; color:#6c757d;">
                    "<?php echo htmlspecialchars($user_data['bio']); ?>"
                </div>
            <?php endif; ?>
        </div>
    </aside>

</div>

</body>
</html>
<?php $conn->close(); ?>