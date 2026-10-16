# Start OCTA FIT local services without launching a second copy of anything already running.
$ErrorActionPreference = "Stop"
$root = "E:\OCTA_FIT"
$php = Join-Path $root "tools\php83\php.exe"

function Test-PortListening([int]$Port) {
    $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    return $null -ne $conn
}

function Start-IfDown([int]$Port, [string]$Name, [scriptblock]$Start) {
    if (Test-PortListening $Port) {
        Write-Host "$Name already running on $Port"
        return
    }
    Write-Host "Starting $Name on $Port"
    & $Start
}

Start-IfDown 3306 "MySQL" {
    Start-Process -FilePath "D:\php_xampp\mysql\bin\mysqld.exe" `
        -ArgumentList "--defaults-file=D:\php_xampp\mysql\bin\my.ini","--standalone" `
        -WorkingDirectory "D:\php_xampp" -WindowStyle Hidden
}

Start-IfDown 8000 "Laravel API" {
    Start-Process -FilePath $php `
        -ArgumentList "artisan","serve","--host=127.0.0.1","--port=8000" `
        -WorkingDirectory (Join-Path $root "backend") -WindowStyle Hidden
}

function Start-Ai([int]$Port, [string]$Folder) {
    $dir = Join-Path $root "ai\$Folder"
    $python = Join-Path $dir ".venv\Scripts\python.exe"
    if (-not (Test-Path $python)) {
        Write-Host "Skipping $Folder (no virtualenv at $python)"
        return
    }
    Start-IfDown $Port $Folder {
        Start-Process -FilePath $python `
            -ArgumentList "-m","uvicorn","app.main:app","--host","127.0.0.1","--port","$Port" `
            -WorkingDirectory $dir -WindowStyle Hidden
    }
}

Start-Ai 8001 "coach"
Start-Ai 8002 "physiotherapist"
Start-Ai 8003 "motion_analyzer"

if (Get-Process -Name octa_fit -ErrorAction SilentlyContinue) {
    Write-Host "Flutter Windows app is already running"
} else {
    Write-Host "Starting Flutter Windows"
    Start-Process -FilePath "flutter" `
        -ArgumentList "run","-d","windows" `
        -WorkingDirectory (Join-Path $root "mobile")
}
