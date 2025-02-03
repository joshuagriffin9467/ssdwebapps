<?php
// Configuration
$apiKey = getenv('ACCESS_TOKEN'); 
$baseUrl = 'https://seaford.incidentiq.com/api/v1.0'; // Base URL for IncidentIQ API

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if an Asset Tag was provided
    $assetTag = $_POST['asset_tag'] ?? '';

    if (!empty($assetTag)) {
        // Step 1: Search for the asset by AssetTag
        $assetUrl = $baseUrl . "/assets/assettag/" . $assetTag; // Searching by AssetTag
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $assetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "Accept: application/json"
        ]);
        $assetResponse = curl_exec($ch);
        curl_close($ch);

        if ($assetResponse) {
            $assetDecoded = json_decode($assetResponse, true);
            if (isset($assetDecoded['ItemCount']) && $assetDecoded['ItemCount'] > 0) {
                $asset = $assetDecoded['Items'][0];
                $modelName = $asset['Model']['Name'] ?? 'N/A';
                $ownerName = $asset['Owner']['FullName'] ?? 'N/A';

                echo "<h3>Asset Information for Asset Tag: $assetTag</h3>";
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
                        <tr>
                            <th>Model</th>
                            <th>Owner</th>
                        </tr>";
                echo "<tr>
                        <td>$modelName</td>
                        <td>$ownerName</td>
                      </tr>";
                echo "</table>";
            } else {
                echo "<p>No asset found with the provided Asset Tag.</p>";
            }
        } else {
            echo "<p>Error retrieving asset information.</p>";
        }
    } else {
        echo "<p>Please enter a valid Asset Tag.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asset Lookup</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body> 
    <h1>Lookup Asset by Asset Tag</h1>
    <form method="post">
        <label for="asset_tag">Asset Tag:</label>
        <input type="text" name="asset_tag" id="asset_tag" placeholder="Enter Asset Tag" required>
        <br><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>