<x-filament-panels::page>
    <style>
        .mermaid-container {
            background: #fff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            min-height: 300px;
        }

        .mermaid-container svg {
            max-width: 100%;
            height: auto;
        }

        .mermaid-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .mermaid-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            padding: 1rem;
            color: #dc2626;
            font-size: 0.875rem;
        }

        .diagram-controls {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .diagram-controls button {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #fff;
            color: #374151;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .diagram-controls button:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        .diagram-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media print {

            .fi-sidebar,
            .fi-topbar,
            .fi-header,
            .fi-header-actions,
            .fi-breadcrumbs,
            .diagram-controls {
                display: none !important;
            }

            .mermaid-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
        }
    </style>

    <div class="space-y-6">
        {{-- Schema-based form controls --}}
        {{ $this->controlSchema }}

        {{-- Diagram display --}}
        @php
            $diagramData = $this->getDiagramData();
        @endphp

        @if ($diagramData)
            <div class="mermaid-container" id="mermaid-container">
                <div class="diagram-controls">
                    <button type="button" id="btn-zoom-in" title="Zoom In">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                        </svg>
                        Zoom In
                    </button>
                    <button type="button" id="btn-zoom-out" title="Zoom Out">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                        </svg>
                        Zoom Out
                    </button>
                    <button type="button" id="btn-zoom-reset" title="Reset Zoom">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        Reset
                    </button>
                    <button type="button" id="btn-download-svg" title="Download as SVG">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download SVG
                    </button>
                    <button type="button" id="btn-download-png" title="Download as PNG">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Download PNG
                    </button>
                </div>

                <div id="mermaid-diagram" class="mermaid-diagram-wrapper">
                    {{-- Mermaid will render here --}}
                </div>

                {{-- Hidden div with raw definition for JS access --}}
                <div id="mermaid-definition" style="display:none;">{{ $diagramData }}</div>
            </div>
        @else
            <div class="mermaid-container">
                <div class="mermaid-loading">
                    <div class="text-center">
                        <svg class="mx-auto mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p>Select a process to visualize its workflow diagram</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @vite('resources/js/mermaid-diagram.js')
</x-filament-panels::page>
