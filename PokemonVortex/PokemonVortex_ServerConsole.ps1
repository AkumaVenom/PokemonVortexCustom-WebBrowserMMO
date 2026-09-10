# Local key editor for the PHP console. Windows PowerShell 5.1 / PowerShell 7.
# Commands travel as JSON over anonymous child-process pipes, never through a
# shell, temporary command file, HTTP endpoint or network listener.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$PhpExe,
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$ConsoleArguments
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($null -eq $ConsoleArguments) { $ConsoleArguments = @() }
$consolePath = Join-Path $PSScriptRoot 'server_console.php'
if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) {
    [Console]::Error.WriteLine('PHP executable was not found. Check the launcher configuration.')
    exit 2
}

# Noninteractive modes do not need a key editor. Native invocation passes an
# argument array; no operator command string is evaluated as PowerShell code.
$direct = [Console]::IsInputRedirected -or [Console]::IsOutputRedirected
foreach ($arg in $ConsoleArguments) {
    if ($arg -eq '--help' -or $arg -eq '--tail-only' -or $arg -eq '--command' -or $arg.StartsWith('--command=')) { $direct = $true }
}
if ($direct) {
    & $PhpExe '-d' 'display_errors=0' '-d' 'display_startup_errors=0' $consolePath @ConsoleArguments
    exit $LASTEXITCODE
}

function ConvertTo-NativeArgument([string]$Value) {
    # Windows CommandLineToArgvW escaping, also understood by PHP's CRT entry.
    if ($Value.Length -eq 0) { return '""' }
    if ($Value -notmatch '[\s"]') { return $Value }
    $escaped = [regex]::Replace($Value, '(\\*)"', '$1$1\"')
    $escaped = [regex]::Replace($escaped, '(\\+)$', '$1$1')
    return '"' + $escaped + '"'
}
function Get-SafeText([string]$Text) {
    $clean = [regex]::Replace($Text, '\x1B(?:\[[0-?]*[ -/]*[@-~]|\][^\x07\x1B]*(?:\x07|\x1B\\)?)', '')
    return [regex]::Replace($clean, '[\x00-\x08\x0B-\x1F\x7F]', '')
}
function Get-EditorWidth {
    return [Math]::Max(20, [Math]::Min([Console]::BufferWidth, [Console]::WindowWidth) - 1)
}
function Clear-EditorLine {
    if (-not $script:editorDrawn) { return }
    [Console]::SetCursorPosition(0, [Console]::CursorTop)
    [Console]::Write((' ' * (Get-EditorWidth)))
    [Console]::SetCursorPosition(0, [Console]::CursorTop)
    $script:editorDrawn = $false
}
function Draw-Editor {
    if ($script:closing) { return }
    $width = Get-EditorWidth
    $prompt = if ($script:busy) { 'vortex* ' } else { 'vortex> ' }
    $available = [Math]::Max(1, $width - $prompt.Length)
    if ($script:cursor -lt $script:viewStart) { $script:viewStart = $script:cursor }
    if ($script:cursor -ge $script:viewStart + $available) { $script:viewStart = $script:cursor - $available + 1 }
    $script:viewStart = [Math]::Max(0, [Math]::Min($script:viewStart, $script:buffer.Length))
    $visibleCount = [Math]::Min($available, $script:buffer.Length - $script:viewStart)
    $visible = $script:buffer.Substring($script:viewStart, $visibleCount)
    [Console]::SetCursorPosition(0, [Console]::CursorTop)
    [Console]::ForegroundColor = if ($script:noColour) { $script:originalColour } else { [ConsoleColor]::Cyan }
    [Console]::Write($prompt)
    [Console]::ForegroundColor = $script:originalColour
    [Console]::Write($visible)
    $padding = $width - $prompt.Length - $visible.Length
    if ($padding -gt 0) { [Console]::Write((' ' * $padding)) }
    [Console]::SetCursorPosition($prompt.Length + $script:cursor - $script:viewStart, [Console]::CursorTop)
    $script:editorDrawn = $true
    $script:lastWidth = $width
}
function Write-ConsoleLine([string]$Text, [string]$Colour = 'white') {
    Clear-EditorLine
    $palette = @{ red = 'Red'; yellow = 'Yellow'; green = 'Green'; cyan = 'Cyan'; dim = 'DarkGray'; magenta = 'Magenta'; white = 'Gray' }
    if (-not $script:noColour -and $palette.ContainsKey($Colour)) {
        [Console]::ForegroundColor = [ConsoleColor]($palette[$Colour])
    } else { [Console]::ForegroundColor = $script:originalColour }
    [Console]::WriteLine((Get-SafeText $Text))
    [Console]::ForegroundColor = $script:originalColour
}
function Send-Request($Request) {
    $json = ConvertTo-Json -InputObject $Request -Compress -Depth 4
    $script:inputWriter.WriteLine($json)
    $script:inputWriter.Flush()
    $script:responseTask = $script:worker.StandardOutput.ReadLineAsync()
    $script:busy = $true
}
function Set-HistoryBuffer([string]$Value) {
    $script:buffer = $Value
    $script:cursor = $Value.Length
    $script:viewStart = 0
}
function Start-LocalWorker {
    $script:worker = New-Object System.Diagnostics.Process
    $script:worker.StartInfo = $script:startInfo
    if (-not $script:worker.Start()) { throw 'PHP worker could not start.' }
    $script:inputWriter = New-Object System.IO.StreamWriter($script:worker.StandardInput.BaseStream, $script:utf8)
    $script:inputWriter.AutoFlush = $true
    # Drain diagnostics asynchronously so stderr cannot block the worker.
    $script:errorTask = $script:worker.StandardError.ReadToEndAsync()
    $script:responseTask = $script:worker.StandardOutput.ReadLineAsync()
    $script:busy = $true
}
function Request-Close {
    $script:closing = $true
    $script:queue.Clear()
    Clear-EditorLine
    Write-ConsoleLine 'Closing after the current command completes. Apache and MySQL remain running.' 'dim'
}

$script:worker = $null
$script:editorDrawn = $false
$script:closing = $false
$script:closeSent = $false
$script:busy = $true
$script:buffer = ''
$script:cursor = 0
$script:viewStart = 0
$script:lastWidth = 0
$script:noColour = $ConsoleArguments -contains '--no-color'
$script:originalColour = [Console]::ForegroundColor
$originalControlC = [Console]::TreatControlCAsInput
$originalInputEncoding = [Console]::InputEncoding
$originalOutputEncoding = [Console]::OutputEncoding
$history = New-Object 'System.Collections.Generic.List[string]'
$historyPosition = 0
$historyDraft = ''
$script:queue = New-Object 'System.Collections.Generic.Queue[string]'
$exitCode = 0
try {
    $script:utf8 = New-Object System.Text.UTF8Encoding($false)
    [Console]::InputEncoding = $utf8
    [Console]::OutputEncoding = $utf8
    [Console]::TreatControlCAsInput = $true
    $script:startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $PhpExe
    $workerArguments = @('-d', 'display_errors=0', '-d', 'display_startup_errors=0', $consolePath, '--worker') + @($ConsoleArguments)
    $startInfo.Arguments = (($workerArguments | ForEach-Object { ConvertTo-NativeArgument $_ }) -join ' ')
    $startInfo.WorkingDirectory = $PSScriptRoot
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.StandardOutputEncoding = $utf8
    $startInfo.StandardErrorEncoding = $utf8
    Start-LocalWorker
    $nextPoll = [DateTime]::UtcNow
    Write-ConsoleLine 'Starting local command console...' 'dim'
    Draw-Editor

    while ($true) {
        if ($null -ne $script:responseTask -and $script:responseTask.IsCompleted) {
            $replyLine = $script:responseTask.GetAwaiter().GetResult()
            $script:responseTask = $null
            $script:busy = $false
            if ($null -eq $replyLine) {
                $script:worker.WaitForExit()
                $exitCode = $script:worker.ExitCode
                if (-not $script:closing) {
                    Write-ConsoleLine 'The PHP command process ended. Any interrupted command must be checked before retrying.' 'yellow'
                    if ($exitCode -eq 0) { $exitCode = 1 }
                }
                break
            }
            $reply = ConvertFrom-Json -InputObject $replyLine
            if ($reply.type -ne 'response') { throw 'Unexpected local worker response.' }
            foreach ($line in $reply.lines) { Write-ConsoleLine ([string]$line.text) ([string]$line.colour) }
            if ($reply.restart) {
                $script:inputWriter.Close()
                $null = $script:worker.StandardOutput.ReadToEndAsync()
                $script:worker.WaitForExit()
                $script:worker.Dispose()
                $script:queue.Clear()
                Set-HistoryBuffer ''
                $historyPosition = $history.Count
                $historyDraft = ''
                Write-ConsoleLine 'Reloading command process. Selected trainer, pending confirmation and queued input are cleared.' 'yellow'
                if ($script:closing) { break }
                Start-LocalWorker
                Draw-Editor
                continue
            }
            if ($reply.exit) {
                $script:closing = $true
                if (-not $reply.ok) { $exitCode = 1 }
                break
            }
            $nextPoll = [DateTime]::UtcNow.AddMilliseconds(200)
            Draw-Editor
        }

        # Exactly one process owns keyboard input. Keep processing keys while a
        # command or log request is in flight so typing never freezes mid-line.
        $keysHandled = 0
        while (-not $script:closing -and [Console]::KeyAvailable -and $keysHandled -lt 64) {
            $key = [Console]::ReadKey($true)
            $keysHandled++
            $control = ($key.Modifiers -band [ConsoleModifiers]::Control) -ne 0
            if ($control -and $key.Key -eq [ConsoleKey]::C) { Request-Close; break }
            if ($control -and $key.Key -eq [ConsoleKey]::D -and $script:buffer.Length -eq 0) { Request-Close; break }
            if ($control -and $key.Key -eq [ConsoleKey]::L) { [Console]::Clear(); $script:editorDrawn = $false; Draw-Editor; continue }
            if ($control -and $key.Key -eq [ConsoleKey]::U) { Set-HistoryBuffer ''; Draw-Editor; continue }
            switch ($key.Key) {
                'Enter' {
                    $command = $script:buffer.Trim()
                    if ($command.Length -gt 0) {
                        if ($script:queue.Count -ge 10) { Write-ConsoleLine 'Command queue is full. Wait for the current commands to finish.' 'yellow'; break }
                        Clear-EditorLine
                        # Never repeat credential/account/confirmation commands
                        # in screen history, nor store them in recall history.
                        $sensitive = $command -match '(?i)(password|passwd|secret|token|credential|\bconfirm\b|\bcreate(?:account)?\b)'
                        if ($sensitive) { Write-ConsoleLine 'vortex> [sensitive command submitted]' 'dim' }
                        else {
                            Write-ConsoleLine ('vortex> ' + $command) 'dim'
                            if ($history.Count -eq 0 -or $history[$history.Count - 1] -ne $command) {
                                $history.Add($command)
                                if ($history.Count -gt 100) { $history.RemoveAt(0) }
                            }
                        }
                        $script:queue.Enqueue($command)
                    }
                    Set-HistoryBuffer ''
                    $historyPosition = $history.Count
                    $historyDraft = ''
                }
                'Backspace' { if ($script:cursor -gt 0) { $script:buffer = $script:buffer.Remove($script:cursor - 1, 1); $script:cursor-- } }
                'Delete' { if ($script:cursor -lt $script:buffer.Length) { $script:buffer = $script:buffer.Remove($script:cursor, 1) } }
                'LeftArrow' { if ($script:cursor -gt 0) { $script:cursor-- } }
                'RightArrow' { if ($script:cursor -lt $script:buffer.Length) { $script:cursor++ } }
                'Home' { $script:cursor = 0 }
                'End' { $script:cursor = $script:buffer.Length }
                'Escape' { Set-HistoryBuffer ''; $historyPosition = $history.Count; $historyDraft = '' }
                'UpArrow' {
                    if ($historyPosition -eq $history.Count) { $historyDraft = $script:buffer }
                    if ($historyPosition -gt 0) { $historyPosition--; Set-HistoryBuffer $history[$historyPosition] }
                }
                'DownArrow' {
                    if ($historyPosition -lt $history.Count) {
                        $historyPosition++
                        if ($historyPosition -eq $history.Count) { Set-HistoryBuffer $historyDraft }
                        else { Set-HistoryBuffer $history[$historyPosition] }
                    }
                }
                default {
                    if (-not [char]::IsControl($key.KeyChar) -and $script:buffer.Length -lt 4096) {
                        $script:buffer = $script:buffer.Insert($script:cursor, [string]$key.KeyChar)
                        $script:cursor++
                    }
                }
            }
            Draw-Editor
        }
        if ($null -eq $script:responseTask) {
            if ($script:closing) {
                if (-not $script:closeSent) { Send-Request @{ type = 'close' }; $script:closeSent = $true }
            } elseif ($script:queue.Count -gt 0) {
                Send-Request @{ type = 'command'; command = $script:queue.Dequeue() }
                Draw-Editor
            } elseif ([DateTime]::UtcNow -ge $nextPoll) {
                Send-Request @{ type = 'poll' }
                # Background log polling does not make the prompt look busy.
                $script:busy = $false
            }
        }
        if (-not $script:closing -and (Get-EditorWidth) -ne $script:lastWidth) { Draw-Editor }
        Start-Sleep -Milliseconds 20
    }
} catch {
    $exitCode = 1
    Write-ConsoleLine 'The local console could not continue. Check PHP, database configuration and the server error log. No command was automatically retried.' 'red'
} finally {
    Clear-EditorLine
    if ($null -ne $script:worker) {
        try {
            if (-not $script:worker.HasExited) {
                $script:inputWriter.Close()
                if ($null -ne $script:responseTask) {
                    # Finish the outstanding read before draining the remaining
                    # stream; never start concurrent reads on StreamReader.
                    $null = $script:responseTask.GetAwaiter().GetResult()
                    $script:responseTask = $null
                }
                $drainTask = $script:worker.StandardOutput.ReadToEndAsync()
                # Let a running transaction complete; closing the pipe asks the
                # worker to leave after it sends the pending command response.
                if (-not $script:worker.WaitForExit(5000)) {
                    [Console]::WriteLine('Waiting for the running command to finish safely...')
                    $script:worker.WaitForExit()
                }
            }
        } catch { }
        $script:worker.Dispose()
    }
    [Console]::ForegroundColor = $script:originalColour
    [Console]::TreatControlCAsInput = $originalControlC
    [Console]::InputEncoding = $originalInputEncoding
    [Console]::OutputEncoding = $originalOutputEncoding
}
exit $exitCode
