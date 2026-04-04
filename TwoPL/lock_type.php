<?php 
function determineLockType($operation) { 
    if ($operation == "READ") { 
        return "SHARED"; 
    } 
    return "EXCLUSIVE"; 
} 
?> 
