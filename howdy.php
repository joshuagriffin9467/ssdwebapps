<?php
// Configuration
$apiKey = getenv('ACCESS_TOKEN'); 
$baseUrl = 'https://seaford.incidentiq.com/api/v1.0'; // IncidentIQ API Base URL
$googleServiceAccountPath = '/var/www/config/service-account-key.json'; // Adjust path to your service account JSON
$customerId = 'C00v19hfe'; // Google Workspace Customer ID

// Google Admin API setup
require_once 'vendor/autoload.php';

function initializeGoogleAdminClient($serviceAccountPath) {
    $client = new Google_Client();
    $client->setAuthConfig($serviceAccountPath);
    $client->addScope(Google_Service_Directory::ADMIN_DIRECTORY_DEVICE_CHROMEOS_READONLY);
    return new Google_Service_Directory($client);
}

$googleService = initializeGoogleAdminClient($googleServiceAccountPath);

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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
                                        <th>Model</th>
                                        <th>Owner</th>
                                        <th>MAC Address</th>
                                    </tr>";

                            foreach ($assetsDecoded['Items'] as $asset) {
                                $modelName = $asset['Model']['Name'] ?? 'N/A';
                                $serialNumber = $asset['SerialNumber'] ?? 'N/A';

                                // Fetch MAC Address from Google Admin API
                                $macAddress = 'N/A';
                                if (!empty($serialNumber)) {
                                    try {
                                        $device = $googleService->chromeosdevices->listChromeosdevices($customerId, [
                                            'query' => "serialNumber:$serialNumber"
                                        ]);
                                        if (!empty($device->getChromeosdevices())) {
                                            $macAddress = $device->getChromeosdevices()[0]->getMacAddress() ?? 'N/A';
                                        } else {
                                            $macAddress = 'No device found for serial: ' . $serialNumber;
                                        }
                                    } catch (Exception $e) {
                                        $macAddress = 'Error retrieving MAC: ' . $e->getMessage();
                                    }
                                }

                                // Output the asset information
                                echo "<tr>
                                        <td>$modelName</td>
                                        <td>$userName</td>
                                        <td>$macAddress</td>
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
    <title>Test IncidentIQ and Google API</title>
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