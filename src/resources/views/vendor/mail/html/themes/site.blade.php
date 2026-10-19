{{-- Email theme built from the site settings; values in $mailBranding are sanitized for CSS. --}}
@php($b = $mailBranding)
/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: {!! $b->fontBody !!};
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: {!! $b->backgroundColor !!};
    color: {!! $b->textColor !!};
    height: 100%;
    line-height: 1.5;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.6;
    text-align: left;
}

a {
    color: {!! $b->linkColor !!};
}

a img {
    border: none;
}

/* Typography */

h1,
h2,
h3 {
    color: {!! $b->headingColor !!};
    font-family: {!! $b->fontHeading !!};
    font-weight: 700;
    margin-top: 0;
    text-align: left;
}

h1 {
    font-size: 22px;
    line-height: 1.3;
}

h2 {
    font-size: 18px;
}

h3 {
    font-size: 16px;
}

p {
    color: {!! $b->textColor !!};
    font-size: 16px;
    margin-top: 0;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {!! $b->backgroundColor !!};
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header */

.header {
    padding: 32px 0 24px;
    text-align: center;
}

.header a {
    color: {!! $b->headingColor !!};
    font-family: {!! $b->fontHeading !!};
    font-size: 22px;
    font-weight: 700;
    text-decoration: none;
}

.header img {
    max-height: 64px;
    width: auto;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {!! $b->backgroundColor !!};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: {!! $b->surfaceColor !!};
    border: 1px solid {!! $b->borderColor !!};
    border-top: 4px solid {!! $b->primaryColor !!};
    border-radius: {!! $b->borderRadius !!};
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.08);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a {
    word-break: break-word;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid {!! $b->borderColor !!};
    margin-top: 28px;
    padding-top: 20px;
}

.subcopy p {
    color: {!! $b->mutedColor !!};
    font-size: 13px;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    color: {!! $b->mutedColor !!};
    font-size: 12px;
    text-align: center;
}

.footer a {
    color: {!! $b->mutedColor !!};
    font-weight: 600;
    text-decoration: none;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 24px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid {!! $b->borderColor !!};
    color: {!! $b->headingColor !!};
    margin: 0;
    padding-bottom: 8px;
    text-align: left;
}

.table td {
    border-bottom: 1px solid {!! $b->borderColor !!};
    color: {!! $b->textColor !!};
    font-size: 15px;
    line-height: 1.4;
    margin: 0;
    padding: 10px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 36px;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: {!! $b->borderRadius !!};
    color: {!! $b->onPrimaryColor !!};
    display: inline-block;
    font-weight: 600;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {!! $b->primaryColor !!};
    border-bottom: 12px solid {!! $b->primaryColor !!};
    border-left: 24px solid {!! $b->primaryColor !!};
    border-right: 24px solid {!! $b->primaryColor !!};
    border-top: 12px solid {!! $b->primaryColor !!};
}

.button-green,
.button-success {
    background-color: #15803D;
    border-bottom: 12px solid #15803D;
    border-left: 24px solid #15803D;
    border-right: 24px solid #15803D;
    border-top: 12px solid #15803D;
    color: #FFFFFF;
}

.button-red,
.button-error {
    background-color: #B91C1C;
    border-bottom: 12px solid #B91C1C;
    border-left: 24px solid #B91C1C;
    border-right: 24px solid #B91C1C;
    border-top: 12px solid #B91C1C;
    color: #FFFFFF;
}

/* Panels */

.panel {
    border-left: {!! $b->panelBorderColor !!} solid 4px;
    margin: 24px 0;
}

.panel-content {
    background-color: {!! $b->panelColor !!};
    color: {!! $b->textColor !!};
    padding: 16px 20px;
}

.panel-content p {
    color: {!! $b->textColor !!};
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Contact form details */

.details th {
    color: {!! $b->mutedColor !!};
    font-size: 13px;
    font-weight: 600;
    padding: 6px 16px 6px 0;
    text-align: left;
    vertical-align: top;
    white-space: nowrap;
}

.details td {
    color: {!! $b->textColor !!};
    font-size: 15px;
    padding: 6px 0;
}

/* Utilities */

.break-all {
    word-break: break-all;
}
