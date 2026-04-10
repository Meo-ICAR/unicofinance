<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #1a1a2e;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        .body {
            padding: 30px;
        }
        .body p {
            margin: 0 0 16px;
            font-size: 15px;
        }
        .credentials-box {
            background: #f0f4ff;
            border: 2px solid #4361ee;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        .credentials-box h3 {
            margin: 0 0 12px;
            font-size: 16px;
            color: #4361ee;
        }
        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #d8e2f7;
        }
        .credential-row:last-child {
            border-bottom: none;
        }
        .credential-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }
        .credential-value {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 700;
            color: #1a1a2e;
            background: #ffffff;
            padding: 4px 10px;
            border-radius: 4px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            margin: 20px 0;
            font-size: 13px;
            color: #664d03;
        }
        .btn {
            display: inline-block;
            background: #4361ee;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            margin: 16px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Benvenuto in {{ config('app.name', 'UnicoFinance') }}!</h1>
        </div>

        <div class="body">
            <p>Gentile <strong>{{ $agent->full_name }}</strong>,</p>

            <p>
                Siamo lieti di darti il benvenuto nella nostra rete agenziale.
                Il tuo profilo è stato attivato con successo e ora hai accesso
                al nostro gestionale interno.
            </p>

            <div class="credentials-box">
                <h3>🔑 Le tue credenziali di accesso</h3>

                <div class="credential-row">
                    <span class="credential-label">Email aziendale</span>
                    <span class="credential-value">{{ $agent->email_corporate }}</span>
                </div>

                <div class="credential-row">
                    <span class="credential-label">Password provvisoria</span>
                    <span class="credential-value">{{ $temporaryPassword }}</span>
                </div>
            </div>

            <div class="warning">
                <strong>⚠️ Importante:</strong> Ti invitiamo a effettuare il primo accesso
                e a modificare la password provvisoria il prima possibile per motivi di sicurezza.
            </div>

            <p>
                Per accedere al gestionale, clicca il pulsante qui sotto:
            </p>

            <a href="{{ url('/login') }}" class="btn">Accedi al Gestionale</a>

            <p>
                Se hai domande o hai bisogno di assistenza, non esitare a contattare
                il reparto HR all'indirizzo
                <a href="mailto:hr@{{ config('app.domain', 'tuazienda.it') }}">
                    hr@{{ config('app.domain', 'tuazienda.it') }}
                </a>.
            </p>

            <p>
                Cordiali saluti,<br>
                <strong>Il Team di {{ config('app.name', 'UnicoFinance') }}</strong>
            </p>
        </div>

        <div class="footer">
            <p>
                Questa email è stata inviata a {{ $agent->email_personal }}
                perché sei stato inserito nella nostra rete agenziale.<br>
                © {{ date('Y') }} {{ config('app.name', 'UnicoFinance') }} — Tutti i diritti riservati.
            </p>
        </div>
    </div>
</body>
</html>
