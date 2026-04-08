<x-filament-panels::page>
    <style>
        @media print {
            .fi-sidebar, 
            .fi-topbar, 
            .fi-header, 
            .fi-header-actions,
            .fi-breadcrumbs,
            .fi-manuale-print-btn,
            .fi-section-header-actions {
                display: none !important;
            }
            
            .fi-main {
                padding: 0 !important;
                margin: 0 !important;
            }

            .fi-main-ctn {
                max-width: none !important;
            }
            
            .fi-manuale-container {
                box-shadow: none !important;
                border: none !important;
            }

            .fi-section {
                break-inside: avoid;
                margin-bottom: 2rem;
            }
        }
    </style>

    <div class="fi-manuale-container">
        {{ $this->infolistSchema }}
    </div>

    @if(request()->query('print'))
        <script>
            window.addEventListener('load', function() {
                setTimeout(() => {
                    window.print();
                }, 1000);
            });
        </script>
    @endif
</x-filament-panels::page>
