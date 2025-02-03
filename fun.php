<!DOCTYPE html>
<html>
<head>
    <title>Hello, World!</title>
</head>
<body>
    <h1>Hello, World!</h1>
    <p>This is a PHP script running on your server.</p>

    <?php
        // Set the timezone to Eastern Time (ET)
        date_default_timezone_set('America/New_York');

        // Get the current time
        $currentTime = date('h:i:s a');

        // Create a dynamic greeting
        $name = "World"; // You can replace this with a user-specific name
        $greeting = "Good " . (date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening')) . ", " . $name . "!";

        // Display the greeting and time
        echo "<p>$greeting</p>";
        echo "<p>Current time (ET): $currentTime</p>";
    ?>
</body>
</html>
