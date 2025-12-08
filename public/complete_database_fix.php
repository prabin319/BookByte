<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Database Fix - BookByte</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .step {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        
        .step h2 {
            color: #374151;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .success {
            background: #dcfce7;
            border-left-color: #10b981;
        }
        
        .success h2 {
            color: #166534;
        }
        
        .error {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        
        .error h2 {
            color: #b91c1c;
        }
        
        .warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        
        .warning h2 {
            color: #92400e;
        }
        
        .info {
            background: #dbeafe;
            border-left-color: #3b82f6;
        }
        
        .info h2 {
            color: #1e40af;
        }
        
        ul {
            margin: 10px 0 10px 20px;
            color: #374151;
        }
        
        li {
            margin: 8px 0;
            line-height: 1.6;
        }
        
        code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #374151;
        }
        
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 10px 0 0;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 14px;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-error {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .progress {
            background: #e5e7eb;
            border-radius: 8px;
            height: 8px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .icon {
            font-size: 24px;
        }
        
        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 15px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🔧</span> Complete Database Fix</h1>
        <p class="subtitle">This script will fix all database schema issues in your BookByte LMS</p>

        <div id="status"></div>
        
        <div class="step warning">
            <h2><span class="icon">⚠️</span> Before You Begin</h2>
            <ul>
                <li>This will modify your database structure</li>
                <li>A backup of the loans table will be created automatically</li>
                <li>After running, please <strong>delete this file</strong> for security</li>
                <li>The process should take less than 10 seconds</li>
            </ul>
        </div>

        <div style="margin-top: 30px;">
            <button class="btn" onclick="runFix()">
                <span class="icon">🚀</span> Run Complete Fix
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='index.php'">
                <span class="icon">↩️</span> Cancel
            </button>
        </div>
    </div>

    <script>
        async function runFix() {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = '<div class="progress"><div class="progress-bar" style="width: 0%"></div></div>';
            
            try {
                const response = await fetch('complete_database_fix_backend.php');
                const result = await response.json();
                
                let html = '';
                let progress = 0;
                const progressBar = document.querySelector('.progress-bar');
                
                // Display results
                for (const step of result.steps) {
                    progress += 20;
                    progressBar.style.width = progress + '%';
                    
                    const stepClass = step.success ? 'success' : 'error';
                    html += `
                        <div class="step ${stepClass}">
                            <h2><span class="icon">${step.icon}</span> ${step.title}</h2>
                            ${step.message}
                        </div>
                    `;
                }
                
                // Final summary
                if (result.overall_success) {
                    html += `
                        <div class="alert" style="background: #dcfce7; border-left: 4px solid #10b981;">
                            <span class="alert-icon">🎉</span>
                            <div class="alert-content">
                                <div class="alert-title" style="color: #166534;">Success!</div>
                                <p style="color: #166534; margin: 0;">All database fixes have been applied successfully. Your BookByte system is now ready to use!</p>
                            </div>
                        </div>
                        
                        <div class="step warning">
                            <h2><span class="icon">⚠️</span> Important: Delete This File!</h2>
                            <p>For security reasons, please delete these files:</p>
                            <ul>
                                <li><code>public/complete_database_fix.php</code> (this file)</li>
                                <li><code>public/complete_database_fix_backend.php</code></li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <a href="index.php?page=books_student" class="btn">📚 Browse Books</a>
                            <a href="index.php?page=dashboard_student" class="btn">🏠 Dashboard</a>
                            <a href="index.php?page=loans_my" class="btn">📄 My Loans</a>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="alert" style="background: #fee2e2; border-left: 4px solid #ef4444;">
                            <span class="alert-icon">❌</span>
                            <div class="alert-content">
                                <div class="alert-title" style="color: #b91c1c;">Some Issues Occurred</div>
                                <p style="color: #b91c1c; margin: 0;">Please check the errors above and contact support if needed.</p>
                            </div>
                        </div>
                    `;
                }
                
                statusDiv.innerHTML = html;
                progressBar.style.width = '100%';
                
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="step error">
                        <h2><span class="icon">❌</span> Error</h2>
                        <p>Failed to run the fix script: ${error.message}</p>
                        <p>Please make sure the backend file exists: <code>complete_database_fix_backend.php</code></p>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>