<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Atto di Nomina - {{ $employee->name }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .title {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 10pt;
            font-style: italic;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
        }
        .grid {
            width: 100%;
            margin-bottom: 20px;
        }
        .grid td {
            vertical-align: top;
            padding: 5px;
        }
        .label {
            font-weight: bold;
            color: #555;
            display: block;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .value {
            font-size: 12pt;
        }
        .function-block {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        .function-name {
            font-size: 13pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }
        .footer {
            margin-top: 60px;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
        }
        .signatures td {
            width: 33%;
            text-align: center;
            vertical-align: bottom;
            padding-top: 50px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 5px;
            padding-top: 5px;
            font-size: 9pt;
        }
        @page {
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Atto di Nomina a Soggetto Autorizzato</div>
        <div class="subtitle">Art. 29 Regolamento UE 2016/679 (GDPR)</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <span class="label">Titolare del Trattamento</span>
                <span class="value">{{ $employee->company->name ?? 'N/D' }}</span>
            </td>
            <td>
                <span class="label">Soggetto Autorizzato</span>
                <span class="value">{{ $employee->name }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <span class="label">Ruolo Aziendale</span>
        <span class="value">In qualità di {{ $employee->role_title }}</span>
    </div>

    <div class="section">
        <div class="section-title">Ambiti di trattamento autorizzati</div>
        @foreach($employee->businessFunctions as $function)
            <div class="function-block">
                <div class="function-name">{{ $function->name }}</div>
                
                <p><strong>Finalità:</strong> {{ $function->purpose }}</p>
                <p><strong>Categorie Dati:</strong> {{ $function->data_categories }}</p>
                <p><strong>Misure di Sicurezza:</strong><br>
                {!! nl2br(e($function->security_measures)) !!}</p>
            </div>
        @endforeach
    </div>

    <div class="footer">
        <table class="signatures">
            <tr>
                <td>
                    <span class="value">{{ now()->format('d/m/Y') }}</span>
                    <div class="signature-line">Data di emissione</div>
                </td>
                <td>
                    <div style="height: 40px;"></div>
                    <div class="signature-line">Firma del Titolare</div>
                </td>
                <td>
                    <div style="height: 40px;"></div>
                    <div class="signature-line">Firma per accettazione</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
