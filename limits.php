<?php
echo "Loaded php.ini = " . (php_ini_loaded_file() ?: "NONE") . "<br><br>";

echo "upload_max_filesize = " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size = " . ini_get('post_max_size') . "<br>";
echo "max_execution_time = " . ini_get('max_execution_time') . "<br>";
echo "max_input_time = " . ini_get('max_input_time') . "<br>";
echo "memory_limit = " . ini_get('memory_limit') . "<br><br>";

echo "<b>PATH</b> = " . htmlspecialchars(getenv("PATH") ?: "") . "<br><br>";

function run_cmd($cmd) {
    $out = @shell_exec($cmd . " 2>&1");
    return $out ? trim($out) : "";
}

echo "<b>where python</b><br><pre>" . htmlspecialchars(run_cmd("where python")) . "</pre>";
echo "<b>where pip</b><br><pre>" . htmlspecialchars(run_cmd("where pip")) . "</pre>";
echo "<b>python exe</b><br><pre>" . htmlspecialchars(run_cmd('python -c "import sys; print(sys.executable)"')) . "</pre>";
echo "<b>python version</b><br><pre>" . htmlspecialchars(run_cmd("python --version")) . "</pre>";

echo "<b>where tesseract</b><br><pre>" . htmlspecialchars(run_cmd("where tesseract")) . "</pre>";
echo "<b>tesseract --version</b><br><pre>" . htmlspecialchars(run_cmd("tesseract --version")) . "</pre>";
