<?php header('Content-Type: text/css'); ?>
/* base styles */
.cloudflared-container {
    margin: 20px auto;
    max-width: 1200px;
}

.status-section {
    background-color: #f8f8f8;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

/* form styling */
.form-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 15px;
    align-items: center;
}

.form-grid label {
    font-weight: bold;
    color: #666;
}

.form-grid > div {
    line-height: 1.5;
    padding: 4px 0;
}

.help-text {
    grid-column: 2;
    color: #666;
    font-size: 0.9em;
    margin-top: 4px;
    margin-bottom: 12px;
}

/* log section styling */
.log-container {
    background-color: #1a1a1a;
    color: #f0f0f0;
    font-family: monospace;
    border-radius: 4px;
    padding: 15px;
    height: 500px;
    overflow-y: auto;
}

.log-toolbar {
    margin-bottom: 15px;
    padding: 10px;
    background-color: #f8f8f8;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #000;
}

.log-line {
    padding: 3px 5px;
    border-bottom: 1px solid #333;
    white-space: pre-wrap;
    font-size: 13px;
    line-height: 1.4;
}

#cloudflared_settings {
    padding: 15px;
}

.tab-nav {
    margin-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.tab-nav a {
    display: inline-block;
    padding: 10px 20px;
    text-decoration: none;
    color: #666;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-nav a:hover {
    color: #333;
}

.tab-nav a.active {
    color: #fff;
    border-bottom-color: #fff;
}
