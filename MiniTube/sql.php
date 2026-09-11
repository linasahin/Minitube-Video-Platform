<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: sql.php
    Description: General-purpose SQL query console. Accepts any SQL query from the
                 user, executes it, and displays results. SELECT results rendered as
                 an HTML table (max 10 rows). INSERT/UPDATE/DELETE shows affected rows.
                 Errors display the error message. The executed query is always echoed
                 above the result.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";
$dbname     = "lina_sahin";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id     = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$query_input = isset($_POST['raw_query']) ? trim($_POST['raw_query']) : '';
$output_html = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query_input)) {

    // Detect if query is a SELECT (to apply 10-row limit and render table)
    $is_select = stripos(ltrim($query_input), 'SELECT') === 0;

    // For SELECT queries, append LIMIT 10 if not already limited by the user
    $exec_query = $query_input;
    if ($is_select && stripos($query_input, 'LIMIT') === false) {
        $exec_query = rtrim($query_input, '; ') . ' LIMIT 10';
    }

    $result = $conn->query($exec_query);

    if ($result === false) {
        // Invalid query: show error message
        $output_html = '<div style="color:#842029; background:#f8d7da; padding:12px; border-radius:4px; font-weight:600;">'
            . '⚠️ SQL Error: ' . htmlspecialchars($conn->error)
            . '</div>';
    } elseif ($result === true) {
        // INSERT / UPDATE / DELETE: show affected rows
        $output_html = '<div style="color:#0f5132; background:#d1e7dd; padding:12px; border-radius:4px; font-weight:600;">'
            . '✅ Query executed successfully. Affected rows: ' . $conn->affected_rows
            . '</div>';
    } else {
        // SELECT: render results as HTML table
        if ($result->num_rows === 0) {
            $output_html = '<p style="color:#6c757d;">Query returned no rows.</p>';
        } else {
            $output_html .= '<p style="font-size:13px; color:#6c757d;">Showing up to 10 rows.</p>';
            $output_html .= '<div style="overflow-x:auto;">';
            $output_html .= '<table border="1" cellpadding="9" style="border-collapse:collapse; width:100%; background:#fff; font-size:14px;">';

            // Column headers from result-set field names
            $output_html .= '<thead><tr style="background:#f1f3f5;">';
            while ($field = $result->fetch_field()) {
                $output_html .= '<th style="text-align:left; padding:10px; color:#495057;">'
                    . htmlspecialchars($field->name) . '</th>';
            }
            $output_html .= '</tr></thead><tbody>';

            // Data rows
            while ($row = $result->fetch_assoc()) {
                $output_html .= '<tr>';
                foreach ($row as $value) {
                    $output_html .= '<td style="padding:9px 10px; border-top:1px solid #f1f3f5;">'
                        . htmlspecialchars($value ?? 'NULL') . '</td>';
                }
                $output_html .= '</tr>';
            }
            $output_html .= '</tbody></table></div>';
        }
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SQL Console - MiniTube</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        header {
            background: #fff;
            padding: 12px 30px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        header a { text-decoration: none; color: #6c757d; font-size: 14px; }
        .container { max-width: 960px; margin: 0 auto; padding: 30px 20px; }
        h2 { color: #212529; margin-top: 0; }
        textarea {
            width: 100%;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            padding: 14px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            resize: vertical;
            background: #fdfdfd;
        }
        .btn-run {
            background: #212529;
            color: #fff;
            border: none;
            padding: 10px 26px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-run:hover { background: #343a40; }

        /* Executed query echo block */
        .query-echo {
            background: #212529;
            color: #f8f9fa;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            padding: 14px 18px;
            border-radius: 4px;
            margin-bottom: 16px;
            word-break: break-all;
            white-space: pre-wrap;
        }
        .result-panel { margin-top: 30px; }
        .result-panel h3 { margin-top: 0; color: #212529; border-bottom: 2px solid #f1f3f5; padding-bottom: 8px; }
    </style>
</head>
<body>

<header>
    <a href="feed.php?user_id=<?php echo $user_id; ?>">← Back to Home Feed</a>
    <span style="font-weight:600; color:#dc3545;">📺 MiniTube</span>
</header>

<div class="container">
    <h2>🖥️ SQL Query Console</h2>
    <p style="color:#6c757d; margin-top:0;">Type any SQL query below and click Execute. SELECT results are limited to 10 rows.</p>

    <form method="POST">
        <textarea name="raw_query" rows="7"
            placeholder="Example: SELECT * FROM USERS LIMIT 5;"><?php echo htmlspecialchars($query_input); ?></textarea><br>
        <button type="submit" class="btn-run">▶ Execute Query</button>
    </form>

    <div class="result-panel">
        <h3>Result</h3>

        <?php if (!empty($query_input)): ?>
            <!-- Spec: echo the executed query above the result -->
            <div class="query-echo"><?php echo htmlspecialchars($query_input); ?></div>
            <?php echo $output_html; ?>
        <?php else: ?>
            <p style="color:#868e96;">No query executed yet. Enter an SQL statement above and click Execute.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
