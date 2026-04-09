<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nomina Responsabile Esterno - {{ $client->name }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 15px;
        }
        .title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0056b3;
        }
        .subtitle {
            font-size: 9pt;
            font-style: italic;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-weight: bold;
            background-color: #eee;
            padding: 5px;
            margin-bottom: 10px;
            display: block;
        }
        .grid {
            width: 100%;
            margin-bottom: 15px;
        }
        .grid td {
            vertical-align: top;
            padding: 5px;
            width: 50%;
        }
        .label {
            font-weight: bold;
            color: #666;
            display: block;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .value {
            font-size: 11pt;
            display: block;
        }
        .function-block {
            border-left: 4px solid #0056b3;
            padding: 10px 15px;
            margin-bottom: 15px;
            background-color: #fcfcfc;
        }
        .function-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .signatures {
            width: 100%;
            margin-top: 50px;
        }
        .signatures td {
            width: 33%;
            text-align: center;
            vertical-align: bottom;
            padding-top: 40px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 5px;
            padding-top: 5px;
            font-size: 8pt;
        }
        .legal-footer {
            margin-top: 40px;
            font-size: 8pt;
            color: #777;
            text-align: justify;
        }
        @page {
            margin: 1.5cm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Atto di Nomina a Responsabile Esterno del Trattamento</div>
        <div class="subtitle">Ai sensi dell'Art. 28 del Regolamento UE 2016/679 (GDPR)</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <span class="label">Titolare del Trattamento (Controller)</span>
                <span class="value">{{ $client->company->name ?? 'N/D' }}</span>
            </td>
            <td>
                <span class="label">Responsabile Esterno (Processor)</span>
                <span class="value">{{ $client->name }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <span class="label">Identificativi Fiscali</span>
        <span class="value">{{ $client->tax_code ?? $client->vat_number ?? 'N/D' }}</span>
    </div>

    <div class="section">
        <div class="section-title">Oggetto della nomina e ambiti di trattamento</div>
        <p>Il Titolare affida al Responsabile le attività di trattamento correlate alle seguenti funzioni/servizi:</p>
        
        @foreach($client->businessFunctions as $function)
            <div class="function-block">
                <div class="function-name">{{ $function->name }}</div>
                
                <p><strong>Finalità:</strong> {{ $function->purpose }}</p>
                <p><strong>Categorie Dati:</strong> {{ $function->data_categories }}</p>
                <p><strong>Istruzioni e Misure:</strong><br>
                {!! nl2br(e($function->security_measures)) !!}</p>
            </div>
        @endforeach
    </div>

    <div class="legal-footer">
        Il Responsabile si impegna a trattare i dati personali esclusivamente per le finalità sopra indicate, attenendosi alle istruzioni impartite dal Titolare e garantendo l'adozione di misure tecniche e organizzative adeguate ai sensi dell'art. 32 del GDPR.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <span class="value">{{ now()->format('d/m/Y') }}</span>
                <div class="signature-line">Data di stipula</div>
            </td>
            <td>
                <div style="height: 30px;"></div>
                <div class="signature-line">Il Titolare del Trattamento</div>
            </td>
            <td>
                <div style="height: 30px;"></div>
                <div class="signature-line">Il Responsabile del Trattamento</div>
            </td>
        </tr>
    </table>
</body>
</html>
