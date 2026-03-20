$intervalSeconds = 30
$commitMessage = "Auto-commit: Workspace changes"

Write-Host "========================================================="
Write-Host "  MicroFin Auto-Push Service Started"
Write-Host "  Checking for changes every $intervalSeconds seconds..."
Write-Host "========================================================="

while ($true) {
    # Check if there are changes
    $gitStatus = git status --porcelain
    if ($gitStatus) {
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Write-Host "[$timestamp] Changes detected. Committing and pushing..." -ForegroundColor DarkYellow
        
        git add .
        git commit -m "$commitMessage ($timestamp)"
        $pushResult = git push origin main 2>&1
        
        Write-Host "[$timestamp] Push complete." -ForegroundColor Green
    }
    
    Start-Sleep -Seconds $intervalSeconds
}