import mermaid from "mermaid";

mermaid.initialize({
    startOnLoad: false,
    theme: "default",
    securityLevel: "loose",
    fontFamily: "Inter, system-ui, -apple-system, sans-serif",
    flowchart: {
        useMaxWidth: true,
        htmlLabels: true,
        curve: "basis",
        padding: 20,
        nodeSpacing: 30,
        rankSpacing: 50,
    },
    state: {
        useMaxWidth: true,
    },
});

let currentZoom = 1;
let isRendering = false;
let lastDefinition = "";

async function renderDiagram() {
    const definitionEl = document.getElementById("mermaid-definition");
    const containerEl = document.getElementById("mermaid-diagram");

    if (!definitionEl || !containerEl) return;

    const definition = definitionEl.textContent?.trim() || "";
    if (!definition) return;
    if (definition === lastDefinition) return; // Skip if same definition

    if (isRendering) return;

    isRendering = true;
    lastDefinition = definition;
    containerEl.innerHTML =
        '<div class="mermaid-loading" style="display:flex;align-items:center;justify-content:center;height:200px;color:#6b7280;">Rendering diagram...</div>';

    try {
        await mermaid.parse(definition);
        const id = "mermaid-" + Date.now();
        const { svg } = await mermaid.render(id, definition);
        containerEl.innerHTML = svg;
        applyZoom();
    } catch (error) {
        console.error("Mermaid render error:", error);
        containerEl.innerHTML = `<div class="mermaid-error" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:1rem;color:#dc2626;font-size:0.875rem;"><p><strong>Error rendering diagram</strong></p><p class="mt-2 text-sm">${error.message || "Unknown error"}</p></div>`;
    } finally {
        isRendering = false;
    }
}

function applyZoom() {
    const svg = document.querySelector("#mermaid-diagram svg");
    if (svg) {
        svg.style.transform = `scale(${currentZoom})`;
        svg.style.transformOrigin = "top left";
        svg.style.transition = "transform 0.2s ease";
    }
}

function downloadSVG() {
    const svg = document.querySelector("#mermaid-diagram svg");
    if (!svg) {
        alert("No diagram to download");
        return;
    }
    const svgData = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([svgData], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "process-diagram.svg";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function downloadPNG() {
    const svg = document.querySelector("#mermaid-diagram svg");
    if (!svg) {
        alert("No diagram to download");
        return;
    }
    const svgData = new XMLSerializer().serializeToString(svg);
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    const img = new Image();
    const size = svg.getBoundingClientRect();
    canvas.width = size.width * 2;
    canvas.height = size.height * 2;
    img.onload = function () {
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "process-diagram.png";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, "image/png");
    };
    img.src =
        "data:image/svg+xml;base64," +
        btoa(unescape(encodeURIComponent(svgData)));
}

function setupControls() {
    document.getElementById("btn-zoom-in")?.addEventListener("click", () => {
        currentZoom = Math.min(currentZoom + 0.2, 3);
        applyZoom();
    });
    document.getElementById("btn-zoom-out")?.addEventListener("click", () => {
        currentZoom = Math.max(currentZoom - 0.2, 0.3);
        applyZoom();
    });
    document.getElementById("btn-zoom-reset")?.addEventListener("click", () => {
        currentZoom = 1;
        applyZoom();
    });
    document
        .getElementById("btn-download-svg")
        ?.addEventListener("click", downloadSVG);
    document
        .getElementById("btn-download-png")
        ?.addEventListener("click", downloadPNG);
}

// Initial render
function init() {
    setupControls();
    renderDiagram();
}

// Hook into Livewire morph to detect when diagram definition changes
if (typeof Livewire !== "undefined") {
    Livewire.hook("morph.updated", ({ el, component }) => {
        if (el.el?.id === "mermaid-definition") {
            lastDefinition = ""; // Force re-render
            setTimeout(renderDiagram, 50);
        }
    });

    // Listen for custom events from checkbox toggle
    Livewire.on("items-visibility-changed", () => {
        lastDefinition = "";
        setTimeout(renderDiagram, 100);
    });
}

// Polling fallback: check for definition changes every 500ms
setInterval(function () {
    const el = document.getElementById("mermaid-definition");
    if (
        el &&
        el.textContent?.trim() &&
        el.textContent.trim() !== lastDefinition
    ) {
        lastDefinition = "";
        renderDiagram();
    }
}, 500);

// Init when DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    setTimeout(init, 100);
}
