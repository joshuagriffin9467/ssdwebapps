<?php
// Configuration
$apiKey = getenv('ACCESS_TOKEN'); 
$baseUrl = 'https://seaford.incidentiq.com/api/v1.0'; // Base URL for IncidentIQ API

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolIdNumber = $_POST['student_id'];

    if (!empty($schoolIdNumber)) {
        // Step 1: Search for the user by SchoolIdNumber
        $searchUrl = $baseUrl . "/search/v2";
        $searchPayload = json_encode([
            "Query" => $schoolIdNumber,
            "Facets" => 4,
            "IncludeMatchedItem" => false
        ]);

        // Set up the cURL request for the search
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "Accept: application/json",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $searchPayload);

        // Execute the search request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $decodedResponse = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['Items'][0]['Id'])) {
                $userId = $decodedResponse['Items'][0]['Id'];
                $userName = $decodedResponse['Items'][0]['Name'] ?? 'Unknown User';

                // Step 2: Use the UserId to search for assets
                $assetsUrl = $baseUrl . "/assets/for/$userId";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $assetsUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $apiKey",
                    "Accept: application/json"
                ]);
                $assetsResponse = curl_exec($ch);
                $assetsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($assetsHttpCode === 200 && $assetsResponse) {
                    $assetsDecoded = json_decode($assetsResponse, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo "<h3>$userName:</h3>";
                        echo "<h3>Assigned Asset Information:</h3>";

                        if ($assetsDecoded['ItemCount'] > 0) {
                            echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
                                    <tr>
                                        <th>Asset Name</th>
                                        <th>Asset Tag</th>
                                        <th>Serial Number</th>
                                        <th>Model</th>
                                        <th>Last Login Date</th>
                                        <th>Recent User</th>
                                    </tr>";

                            foreach ($assetsDecoded['Items'] as $asset) {
                                $assetName = $asset['Name'] ?? 'N/A';
                                $assetTag = $asset['AssetTag'] ?? 'N/A';
                                $serialNumber = $asset['SerialNumber'] ?? 'N/A';
                                $modelName = $asset['Model']['Name'] ?? 'N/A';

                                $lastLoginDate = 'N/A';
                                $recentUserName = 'N/A';
                                if (isset($asset['CustomFieldValues']) && is_array($asset['CustomFieldValues'])) {
                                    foreach ($asset['CustomFieldValues'] as $field) {
                                        if (isset($field['Value']) && !empty($field['Value'])) {
                                            $customFieldsData = json_decode($field['Value'], true);
                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                foreach ($customFieldsData as $customField) {
                                                    if (isset($customField['LastLoginDate'])) {
                                                        $lastLoginDate = $customField['LastLoginDate'];
                                                    }
                                                    if (isset($customField['RecentUserEmail'])) {
                                                        $recentUserEmail = $customField['RecentUserEmail'];

                                                        // Step: Search for the user by their email
                                                        $emailSearchUrl = $baseUrl . "/search/v2";
                                                        $emailSearchPayload = json_encode([
                                                            "Query" => $recentUserEmail,
                                                            "Facets" => 4,
                                                            "IncludeMatchedItem" => false
                                                        ]);

                                                        $ch = curl_init();
                                                        curl_setopt($ch, CURLOPT_URL, $emailSearchUrl);
                                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                            "Authorization: Bearer $apiKey",
                                                            "Accept: application/json",
                                                            "Content-Type: application/json"
                                                        ]);
                                                        curl_setopt($ch, CURLOPT_POST, true);
                                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $emailSearchPayload);

                                                        $emailResponse = curl_exec($ch);
                                                        $emailHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                        curl_close($ch);

                                                        if ($emailHttpCode === 200 && $emailResponse) {
                                                            $emailDecodedResponse = json_decode($emailResponse, true);
                                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                                $recentUserName = $emailDecodedResponse['Items'][0]['Name'] ?? 'Unknown User';
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                // Highlight row if the recent user is different from the requested user
                                $rowStyle = ($recentUserName !== $userName) ? "style='background-color: red; color: white;'" : "";

                                // Output the asset information
                                echo "<tr $rowStyle>
                                        <td>$assetName</td>
                                        <td>$assetTag</td>
                                        <td>$serialNumber</td>
                                        <td>$modelName</td>
                                        <td>$lastLoginDate</td>
                                        <td>$recentUserName</td>
                                      </tr>";
                            }

                            echo "</table>";
                        } else {
                            echo "<p>No assets found for this user.</p>";
                        }
                    } else {
                        echo "<p>Error decoding assets response.</p>";
                    }
                } else {
                    echo "<p>Error retrieving user assets.</p>";
                }
            } else {
                echo "<p>No UserId found for the provided Student ID.</p>";
            }
        } else {
            echo "<p>Error searching for user. Status Code: $httpCode</p>";
        }
    } else {
        echo "<p>Please enter a valid Student ID.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Device Lookup</title>
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
    <h1>Lookup Devices by Student ID</h1>
    <form method="post">
        <label for="student_id">Student ID:</label>
        <input type="text" name="student_id" id="student_id" required>
        <button type="submit">Submit</button>
    </form>
</body>
</html>