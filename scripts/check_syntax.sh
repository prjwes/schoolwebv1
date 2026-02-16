#!/bin/bash

echo "Checking PHP syntax for modified files..."
echo "=========================================="

# Check main modified files
php -l /vercel/share/v0-project/dashboard.php
php -l /vercel/share/v0-project/fees.php
php -l /vercel/share/v0-project/exams.php
php -l /vercel/share/v0-project/student_details.php

echo ""
echo "All syntax checks complete!"
