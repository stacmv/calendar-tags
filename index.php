<?php
/**
 * Calendar Tag Scheduler - Web Interface Landing Page
 *
 * Provides navigation to the main features
 */

require_once 'auth.php';

$auth = new Auth();
$auth->requireAuth();

$username = $auth->getUsername();
$userDir = $auth->getUserDir();
$configFile = $userDir . '/config.php';
$configExists = file_exists($configFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Tag Scheduler</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 36px;
            text-align: center;
        }

        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 18px;
            text-align: center;
            line-height: 1.6;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature-card {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .feature-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #667eea;
            color: white;
        }

        .btn-secondary:hover {
            background: #5568d3;
        }

        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
        }

        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }

        .cli-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #667eea;
        }

        .cli-info h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .cli-info code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .cli-info p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: right; margin-bottom: 20px;">
            <span style="color: #666; margin-right: 10px;">User: <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;">Logout</a>
        </div>

        <h1>Calendar Tag Scheduler</h1>
        <p class="subtitle">
            A priority-based scheduling system for managing your content consumption across daily time slots
        </p>

        <?php if (!$configExists): ?>
        <div class="alert">
            <div class="alert-title">Configuration Required</div>
            <p>No configuration file found. Please use the Config Editor to create your settings.</p>
        </div>
        <?php endif; ?>

        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <div class="feature-title">View Schedule</div>
                <div class="feature-description">
                    Generate and view your content schedule with statistics and download ICS calendar file
                </div>
                <?php if ($configExists): ?>
                    <a href="scheduler.php" class="btn btn-primary">Open Schedule</a>
                <?php else: ?>
                    <span class="btn btn-disabled">Configure First</span>
                <?php endif; ?>
            </div>

            <div class="feature-card">
                <div class="feature-icon">⚙️</div>
                <div class="feature-title">Config Editor</div>
                <div class="feature-description">
                    Edit time slots, tags, priorities, and scheduling settings through a user-friendly interface
                </div>
                <a href="config-editor.php" class="btn btn-secondary">Open Editor</a>
            </div>
        </div>

        <div class="cli-info">
            <h3>Command Line Usage</h3>
            <p>You can also run the scheduler from the command line:</p>
            <p><code>php scheduler.php</code></p>
            <p>This will generate a formatted ASCII table and create the calendar_tags.ics file.</p>
        </div>

        <div class="footer">
            <p><strong>Calendar Tag Scheduler v2</strong></p>
            <p>Priority-based hybrid scheduling algorithm with ICS calendar export</p>
        </div>
    </div>
</body>
</html>
