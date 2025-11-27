$ftpHost = 'ftp://ftp.trentinoimmobiliare.it'
$username = 'wp@trentinoimmobiliare.it'
$password = 'WpNovacom@1125'

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}

$dirUrl = "$ftpHost/public_html/wp-content/plugins/realestate-sync-plugin/data"

Write-Host "Creating directory: $dirUrl"

$request = [System.Net.FtpWebRequest]::Create($dirUrl)
$request.Credentials = New-Object System.Net.NetworkCredential($username, $password)
$request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory

try {
    $response = $request.GetResponse()
    Write-Host "✅ Directory created successfully" -ForegroundColor Green
    $response.Close()
} catch {
    if ($_.Exception.InnerException.Response.StatusCode -eq 'FileUnavailable') {
        Write-Host "⚠️  Directory already exists" -ForegroundColor Yellow
    } else {
        Write-Host "❌ Error: $_" -ForegroundColor Red
    }
}
