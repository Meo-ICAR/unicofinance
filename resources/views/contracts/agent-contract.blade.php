<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000000;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            margin: 0 0 5px;
            text-transform: uppercase;
        }
        .header p {
            font-size: 10pt;
            color: #555;
            margin: 0;
        }
        .section {
            margin-bottom: 24px;
        }
        .section-title {
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .data-table td {
            padding: 8px 12px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        .data-table td:first-child {
            font-weight: bold;
            width: 35%;
            background-color: #f5f5f5;
        }
        .signature-block {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block div {
            width: 45%;
            border-top: 1px solid #000;
            padding-top: 8px;
            text-align: center;
            font-size: 10pt;
        }
        .footer {
            margin-top: 40px;
            font-size: 8pt;
            color: #888;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Contratto di Collaborazione Agenziale</h1>
        <p>{{ config('app.name', 'UnicoFinance') }} — Documento generato automaticamente il {{ now()->format('d/m/Y') }}</p>
    </div>

    <div class="section">
        <div class="section-title">1. Dati del Contraente</div>
        <table class="data-table">
            <tr>
                <td>Nome e Cognome</td>
                <td>{{ $agent->last_name }} {{ $agent->first_name }}</td>
            </tr>
            <tr>
                <td>Codice Fiscale</td>
                <td>{{ $agent->fiscal_code }}</td>
            </tr>
            <tr>
                <td>Email Personale</td>
                <td>{{ $agent->email_personal }}</td>
            </tr>
            <tr>
                <td>Telefono</td>
                <td>{{ $agent->phone ?? 'N/D' }}</td>
            </tr>
            <tr>
                <td>N. Iscrizione OAM</td>
                <td>{{ $agent->oam_number ?? 'Non ancora iscritto' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. Oggetto del Contratto</div>
        <p>
            La Società <strong>{{ config('app.name', 'UnicoFinance') }}</strong>, con sede legale in Italia,
            P.IVA {{ config('app.vat_number', 'N/D') }>, conferisce al Contraente l'incarico di agente
            per la promozione e la commercializzazione dei prodotti e servizi finanziari della Società,
            nel rispetto della normativa OAM/IVASS vigente.
        </p>
    </div>

    <div class="section">
        <div class="section-title">3. Obblighi del Contraente</div>
        <p>Il Contraente si impegna a:</p>
        <ol>
            <li>Operare con diligenza, correttezza e trasparenza nei confronti della clientela;</li>
            <li>Rispettare le normative OAM/IVASS e le policy interne della Società in materia di Privacy GDPR (Reg. UE 2016/679);</li>
            <li>Mantenere aggiornata la propria iscrizione al registro OAM per tutta la durata del rapporto;</li>
            <li>Partecipare ai programmi di formazione continua previsti dalla Società.</li>
        </ol>
    </div>

    <div class="section">
        <div class="section-title">4. Durata e Recesso</div>
        <p>
            Il presente contratto ha durata indeterminata a decorrere dalla data di sottoscrizione.
            Ciasuna parte potrà recedere dal contratto con preavviso di 90 (novanta) giorni,
            da comunicarsi mediante raccomandata A/R o PEC.
        </p>
    </div>

    <div class="section">
        <div class="section-title">5. Provvigioni</div>
        <p>
            Il Contraente avrà diritto a provvigioni calcolate secondo il piano provvigionale
            vigente presso la Società, consultabile presso il reparto amministrativo.
        </p>
    </div>

    <div class="signature-block">
        <div>
            <strong>La Società</strong><br>
            {{ config('app.name', 'UnicoFinance') }}
        </div>
        <div>
            <strong>Il Contraente</strong><br>
            {{ $agent->full_name }}
        </div>
    </div>

    <div class="footer">
        <p>
            Documento generato automaticamente dal sistema BPM di {{ config('app.name', 'UnicoFinance') }}.
            ID Agente: #{{ $agent->id }} — {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>
</body>
</html>
