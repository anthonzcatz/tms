<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/theme.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Open Sans', sans-serif;
        }
        .access-denied-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            margin-bottom: 2rem;
        }
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="access-denied-card">
        <div class="icon">🔒</div>
        <h1>Access Denied</h1>
        <p>You don't have permission to access Role Dashboard Settings. Only SUPER_ADMIN can manage these settings.</p>
        <a href="<?php echo BASE_URL; ?>/admin/dashboard/analytics" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>
