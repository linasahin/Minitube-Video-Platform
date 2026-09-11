<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: generate_data.php
    Description: Reads data from text files (first_names.txt, last_names.txt, etc.)
                 and generates seed.sql with INSERT statements for all tables.
                 Minimum: 100 users, 50 channels, 200 videos, 120 subscriptions,
                 150 comments (at least 20 replies).
*/

// ── Helper: read a .txt file line by line, return trimmed non-empty lines ──────
function read_lines($filename) {
    if (!file_exists($filename)) {
        die("Error: required input file '$filename' not found. Please create it.");
    }
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('trim', $lines);
}

// ── Read all input text files ─────────────────────────────────────────────────
$first_names  = read_lines('first_names.txt');
$last_names   = read_lines('last_names.txt');
$countries    = read_lines('countries.txt');
$categories   = read_lines('categories.txt');
$video_titles = read_lines('video_titles.txt');
$bio_lines    = read_lines('bios.txt');
$channel_descs = read_lines('channel_descs.txt');

// ── Real online image/video URLs (no local assets) ────────────────────────────
$user_images = [
    'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&q=80',
    'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=150&q=80',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=150&q=80',
    'https://images.unsplash.com/photo-1527980965255-d3b416303d12?auto=format&fit=crop&w=150&q=80',
];
$channel_images = [
    'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=300&q=80',
    'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=300&q=80',
    'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?auto=format&fit=crop&w=300&q=80',
];
$youtube_videos = [
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
    'https://www.youtube.com/watch?v=9bZkp7q19f0',
    'https://www.youtube.com/watch?v=ZZ5LpwO-An4',
    'https://www.youtube.com/watch?v=JGwWNGJdvx8',
];

$sql_statements = [];

// ── 1. GENERATE USERS (minimum 100, generating 110) ──────────────────────────
$used_usernames = [];
for ($i = 1; $i <= 110; $i++) {
    $fname    = $first_names[($i - 1) % count($first_names)];
    $lname    = $last_names[($i - 1)  % count($last_names)];
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fname)) . $i;

    // Ensure unique username
    while (in_array($username, $used_usernames)) {
        $username .= '_' . $i;
    }
    $used_usernames[] = $username;

    $full_name = addslashes("$fname $lname");
    $password  = password_hash("password123", PASSWORD_DEFAULT);
    $img       = $user_images[($i - 1) % count($user_images)];
    $email     = addslashes($username . "@example.com");
    $country   = addslashes($countries[($i - 1) % count($countries)]);
    $joined    = date('Y-m-d', strtotime("-" . rand(30, 1000) . " days"));
    $bio       = addslashes($bio_lines[($i - 1) % count($bio_lines)]);

    $sql_statements[] = "INSERT INTO USERS (user_id, username, password, user_image, full_name, email, country, joined_on, bio) "
        . "VALUES ($i, '$username', '$password', '$img', '$full_name', '$email', '$country', '$joined', '$bio')";
}

// ── 2. GENERATE CHANNELS (minimum 50, generating 55) ─────────────────────────
// Rule: each channel has a unique owner (owner_ids 1..55, one channel per user)
for ($i = 1; $i <= 55; $i++) {
    $c_img   = $channel_images[($i - 1) % count($channel_images)];
    $fname   = $first_names[($i - 1) % count($first_names)];
    $name    = addslashes($fname . "'s Channel");
    $desc    = addslashes($channel_descs[($i - 1) % count($channel_descs)]);
    $created = date('Y-m-d', strtotime("-" . rand(10, 500) . " days"));
    $cat     = addslashes($categories[($i - 1) % count($categories)]);

    $sql_statements[] = "INSERT INTO CHANNELS (channel_id, owner_id, channel_image, name, description, created_on, category) "
        . "VALUES ($i, $i, '$c_img', '$name', '$desc', '$created', '$cat')";
}

// ── 3. GENERATE VIDEOS (minimum 200, generating 210) ─────────────────────────
for ($i = 1; $i <= 210; $i++) {
    $channel_id = (($i - 1) % 55) + 1; // distribute evenly across channels
    $title      = addslashes($video_titles[($i - 1) % count($video_titles)] . " #$i");
    $desc       = addslashes("An in-depth look at interesting topics. Video number $i.");
    $url        = $youtube_videos[($i - 1) % count($youtube_videos)];
    $duration   = rand(60, 3600);
    $uploaded   = date('Y-m-d', strtotime("-" . rand(1, 365) . " days"));
    // Range covers all three badge tiers: New (<100), Trending (>=100), Popular (>=1000)
    $views      = rand(0, 3000);
    $likes      = rand(0, max(1, $views));

    $sql_statements[] = "INSERT INTO VIDEOS (video_id, channel_id, title, description, url, duration_seconds, uploaded_at, view_count, like_count) "
        . "VALUES ($i, $channel_id, '$title', '$desc', '$url', $duration, '$uploaded', $views, $likes)";
}

// ── 4. GENERATE SUBSCRIPTIONS (minimum 120, generating 130) ──────────────────
$existing_subs = [];
$sub_count     = 0;
$sub_id        = 1;
// Use sequential pairs to guarantee no duplicates and reach the minimum
for ($uid = 1; $uid <= 110 && $sub_count < 130; $uid++) {
    for ($cid = 1; $cid <= 55 && $sub_count < 130; $cid++) {
        $combo = "{$uid}-{$cid}";
        if (!in_array($combo, $existing_subs)) {
            $existing_subs[] = $combo;
            $subbed_at = date('Y-m-d', strtotime("-" . rand(1, 300) . " days"));
            $sql_statements[] = "INSERT INTO SUBSCRIPTIONS (subscription_id, subscriber_id, channel_id, subscribed_at) "
                . "VALUES ($sub_id, $uid, $cid, '$subbed_at')";
            $sub_id++;
            $sub_count++;
        }
    }
}

// ── 5. GENERATE COMMENTS (minimum 150: 130 top-level + 25 replies = 155) ─────
$comment_bodies = [
    "Great video, really enjoyed it!",
    "Very informative, thank you for sharing.",
    "This is exactly what I was looking for.",
    "Amazing content, keep it up!",
    "I learned so much from this video.",
    "Well explained and easy to follow.",
    "Subscribed! This channel is fantastic.",
    "Could you make a follow-up video on this topic?",
    "Best video I have watched this week.",
    "Really helpful, bookmarked for later.",
];
$reply_bodies = [
    "I totally agree with you!",
    "Thanks for sharing your thoughts.",
    "Good point, I had not thought of that.",
    "Exactly my reaction too!",
    "I disagree, but I see where you are coming from.",
];

// Top-level comments (parent_comment_id = NULL)
for ($i = 1; $i <= 130; $i++) {
    $video_id = (($i - 1) % 210) + 1;
    $user_id  = (($i - 1) % 110) + 1;
    $body     = addslashes($comment_bodies[($i - 1) % count($comment_bodies)]);
    $posted   = date('Y-m-d', strtotime("-" . rand(1, 200) . " days"));

    $sql_statements[] = "INSERT INTO COMMENTS (comment_id, video_id, user_id, parent_comment_id, body, posted_at) "
        . "VALUES ($i, $video_id, $user_id, NULL, '$body', '$posted')";
}

// Reply comments (parent_comment_id references a top-level comment id 1..130)
for ($i = 131; $i <= 155; $i++) {
    $parent_id = (($i - 131) % 130) + 1; // references top-level comments only
    $video_id  = (($i - 1) % 210) + 1;
    $user_id   = (($i - 1) % 110) + 1;
    $body      = addslashes($reply_bodies[($i - 131) % count($reply_bodies)]);
    $posted    = date('Y-m-d', strtotime("-" . rand(1, 100) . " days"));

    $sql_statements[] = "INSERT INTO COMMENTS (comment_id, video_id, user_id, parent_comment_id, body, posted_at) "
        . "VALUES ($i, $video_id, $user_id, $parent_id, '$body', '$posted')";
}

// ── Write all statements to seed.sql ─────────────────────────────────────────
$output = implode(";\n", $sql_statements) . ";";
file_put_contents('seed.sql', $output);

echo "Success: seed.sql generated with 110 users, 55 channels, 210 videos, 130 subscriptions, 155 comments (25 replies).";
?>
