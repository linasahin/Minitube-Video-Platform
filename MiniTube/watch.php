<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: watch.php
    Description: Displays a single video with all required info (title, channel link,
                 uploader country, formatted duration, upload date, view count, popularity
                 badge via SQL CASE). Increments view count on every load.
                 Shows comment thread via a single SQL self-join query.
                 Allows posting new top-level comments at the bottom.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";
$dbname     = "lina_sahin";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id  = isset($_GET['user_id'])  ? intval($_GET['user_id'])  : 0;
$video_id = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;

$conn->query("UPDATE VIDEOS SET view_count = view_count + 1 WHERE video_id = $video_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $body      = $conn->real_escape_string(trim($_POST['body']));
    $parent_id = !empty($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : "NULL";
    $today     = date('Y-m-d');
    $conn->query("INSERT INTO COMMENTS (video_id, user_id, parent_comment_id, body, posted_at)
                  VALUES ($video_id, $user_id, $parent_id, '$body', '$today')");
    header("Location: watch.php?user_id=$user_id&video_id=$video_id");
    exit();
}

$video_sql = "
    SELECT V.*,
           C.name       AS channel_name,
           U.country    AS uploader_country,
           CASE
               WHEN V.view_count >= 1000 THEN 'Popular'
               WHEN V.view_count >= 100  THEN 'Trending'
               ELSE 'New'
           END AS popularity_badge
    FROM VIDEOS V
    JOIN CHANNELS C ON V.channel_id = C.channel_id
    JOIN USERS U    ON C.owner_id   = U.user_id
    WHERE V.video_id = $video_id
";
$video_res = $conn->query($video_sql);
$video     = $video_res ? $video_res->fetch_assoc() : null;
if (!$video) { die("Video not found."); }

$comments_sql = "
    SELECT
        parent.comment_id   AS p_id,
        parent.body         AS p_body,
        parent.posted_at    AS p_posted_at,
        pu.username         AS p_username,
        child.comment_id    AS c_id,
        child.body          AS c_body,
        child.posted_at     AS c_posted_at,
        cu.username         AS c_username
    FROM COMMENTS parent
    JOIN USERS pu ON parent.user_id = pu.user_id
    LEFT JOIN COMMENTS child ON child.parent_comment_id = parent.comment_id
    LEFT JOIN USERS cu       ON child.user_id = cu.user_id
    WHERE parent.video_id = $video_id
      AND parent.parent_comment_id IS NULL
    ORDER BY parent.comment_id DESC, child.comment_id ASC
";
$comments_res = $conn->query($comments_sql);

$comments = [];
if ($comments_res) {
    while ($row = $comments_res->fetch_assoc()) {
        $pid = $row['p_id'];
        if (!isset($comments[$pid])) {
            $comments[$pid] = [
                'id'       => $pid,
                'body'     => $row['p_body'],
                'posted'   => $row['p_posted_at'],
                'username' => $row['p_username'],
                'replies'  => [],
            ];
        }
        if ($row['c_id'] !== null) {
            $comments[$pid]['replies'][] = [
                'id'       => $row['c_id'],
                'body'     => $row['c_body'],
                'posted'   => $row['c_posted_at'],
                'username' => $row['c_username'],
            ];
        }
    }
}

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
    <title><?php echo htmlspecialchars($video['title']); ?> - MiniTube</title>
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
        .container { max-width: 860px; margin: 0 auto; padding: 25px 20px; }
        .player-box {
            background: #1a1a2e;
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 15px;
            gap: 10px;
        }
        .player-box a {
            color: #ffc107;
            font-weight: 600;
            word-break: break-all;
        }
        .details-box {
            background: #fff;
            border-radius: 6px;
            padding: 20px 24px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            margin-top: 16px;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            margin-bottom: 10px;
        }
        .badge-New      { background: #e9ecef; color: #495057; }
        .badge-Trending { background: #fff3cd; color: #856404; }
        .badge-Popular  { background: #f8d7da; color: #842029; }
        .details-box h1 { margin: 0 0 12px 0; font-size: 20px; color: #212529; }
        .meta-row { font-size: 13px; color: #6c757d; margin: 4px 0; }
        .meta-row a { color: #007bff; text-decoration: none; font-weight: 600; }
        .comments-section {
            background: #fff;
            border-radius: 6px;
            padding: 20px 24px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            margin-top: 20px;
        }
        .comments-section h3 { margin-top: 0; }
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }
        .comment-form button {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
        }
        .comment-item {
            border-left: 3px solid #dee2e6;
            padding-left: 14px;
            margin-bottom: 18px;
        }
        .comment-item .author { font-weight: 600; font-size: 13px; color: #212529; }
        .comment-item .date   { font-size: 12px; color: #868e96; margin-left: 8px; }
        .comment-item .body   { margin: 5px 0; font-size: 14px; color: #333; }
        .reply-item {
            margin-left: 25px;
            margin-top: 10px;
            border-left: 2px solid #f1f3f5;
            padding-left: 12px;
        }
        .reply-item .author { font-weight: 600; font-size: 13px; color: #495057; }
        .reply-item .body   { margin: 4px 0; font-size: 13px; color: #555; }
    </style>
</head>
<body>

<header>
    <a href="feed.php?user_id=<?php echo $user_id; ?>">← Back to Home Feed</a>
    <span style="font-weight:600; color:#dc3545;">📺 MiniTube</span>
</header>

<div class="container">

    <div class="player-box">
        <span>🎬 Watch on YouTube</span>
        <a href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank">
            <?php echo htmlspecialchars($video['url']); ?>
        </a>
    </div>

    <div class="details-box">
        <div class="badge badge-<?php echo $video['popularity_badge']; ?>">
            <?php echo $video['popularity_badge']; ?>
        </div>
        <h1><?php echo htmlspecialchars($video['title']); ?></h1>
        <div class="meta-row">
            Channel:
            <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video['channel_id']; ?>">
                <?php echo htmlspecialchars($video['channel_name']); ?>
            </a>
        </div>
        <div class="meta-row">🌍 Uploaded from: <?php echo htmlspecialchars($video['uploader_country']); ?></div>
        <div class="meta-row">⏱ Duration: <?php echo format_duration($video['duration_seconds']); ?></div>
        <div class="meta-row">📅 Uploaded: <?php echo htmlspecialchars($video['uploaded_at']); ?></div>
        <div class="meta-row">
            👁 Views: <strong><?php echo number_format($video['view_count']); ?></strong>
            &nbsp;|&nbsp;
            👍 Likes: <strong><?php echo number_format($video['like_count']); ?></strong>
        </div>
        <?php if (!empty($video['description'])): ?>
            <p style="margin-top:14px; color:#555; line-height:1.6;">
                <?php echo htmlspecialchars($video['description']); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="comments-section">
        <h3>💬 Comments (<?php echo count($comments); ?> top-level)</h3>

        <!-- Comment thread first, as spec requires form at the bottom -->
        <?php if (empty($comments)): ?>
            <p style="color:#6c757d;">No comments yet. Be the first to comment!</p>
        <?php else: ?>
            <?php foreach ($comments as $c): ?>
                <div class="comment-item">
                    <span class="author">@<?php echo htmlspecialchars($c['username']); ?></span>
                    <span class="date"><?php echo htmlspecialchars($c['posted']); ?></span>
                    <p class="body"><?php echo htmlspecialchars($c['body']); ?></p>
                    <?php foreach ($c['replies'] as $r): ?>
                        <div class="reply-item">
                            <span class="author">↩ @<?php echo htmlspecialchars($r['username']); ?></span>
                            <span class="date" style="font-size:12px; color:#868e96; margin-left:6px;"><?php echo htmlspecialchars($r['posted']); ?></span>
                            <p class="body"><?php echo htmlspecialchars($r['body']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Spec: form at the bottom of the page -->
        <div class="comment-form" style="margin-top:25px; border-top:1px solid #dee2e6; padding-top:20px;">
            <h4 style="margin:0 0 10px 0; color:#212529;">Leave a Comment</h4>
            <form method="POST">
                <input type="hidden" name="parent_comment_id" value="">
                <textarea name="body" rows="3" placeholder="Write a comment..." required></textarea><br>
                <button type="submit" name="submit_comment">Post Comment</button>
            </form>
        </div>
    </div>

</div>

</body>
</html>
<?php $conn->close(); ?>