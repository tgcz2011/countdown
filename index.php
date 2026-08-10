<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>高考倒计时</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        // 简单测试 - 确认JS可执行
        console.log('index.php 开始加载');
    </script>
</head>
<body data-page-type="main">
    <!-- 背景层 -->
    <div class="background-layer"></div>

    <!-- 倒计时容器 -->
    <div class="countdown-container">
        <h1 class="countdown-title">距离高考还有</h1>
        <div class="countdown-display">00:00:00:00</div>
    </div>

    <!-- 励志话语 -->
    <div class="motivation-container">
        <p class="motivation-text"></p>
    </div>

    <!-- 当前时间 -->
    <div class="current-time"></div>

    <!-- JavaScript -->
    <script src="js/script.js?v=20260810c"></script>
    <script>
        console.log('index.php 脚本加载完成');
    </script>
</body>
</html>
