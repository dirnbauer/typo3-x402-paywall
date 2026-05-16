"use client";

/**
 * X402BlogSimulator
 *
 * Embeddable GSAP-animated x402 protocol simulator for the webconsulting.at blog.
 * Placed after the "Architektur" section (#architektur).
 *
 * Usage in blog MDX/content:
 *   import { X402BlogSimulator } from "@webconsulting/typo3-x402-react";
 *   <X402BlogSimulator />
 *
 * Colors: webconsulting.at CI
 *   Primary teal:  #1b7a95
 *   Light teal:    #66c4e1
 *   Pale teal bg:  #c5e4ed
 *   Dark teal:     #155d73
 */

import { useEffect, useRef, useState, useCallback } from "react";

// ── Brand tokens ──────────────────────────────────────────────────────────────
const C = {
  primary:     "#1b7a95",
  primaryLight:"#66c4e1",
  primaryPale: "#c5e4ed",
  primaryDark: "#155d73",
  primaryBg:   "#e8f4f8",
  dark:        "#07151f",
  dark2:       "#0d2235",
  dark3:       "#122d44",
  text:        "#e8f4f8",
  muted:       "#7fb8cc",
  success:     "#22c55e",
  error:       "#ef4444",
  warning:     "#f59e0b",
} as const;

// ── Scenario data (German — matches blog language) ────────────────────────────
const SCENARIOS = [
  {
    id: "no-payment",
    label: "1 — Ohne Zahlung",
    badgeColor: C.error,
    curl: `GET /premium-artikel HTTP/2
Host: typo3.example.at
User-Agent: AI-Agent/1.0
Accept: application/json`,
    response: `HTTP/2 402 Payment Required
Content-Type: application/json
PAYMENT-REQUIRED: eyJzY2hlbWUiOiJleGFjdCIs
  Im5ldHdvcmsiOiJlaXAxNTU6ODQ1My...

{
  "status": 402,
  "human_readable": {
    "price": "0.001 USDC",
    "network": "Base (Mainnet)",
    "description": "Premium Artikel"
  }
}`,
    steps: [
      {
        phase: "client",
        icon: "→",
        title: "Anfrage ohne Zahlung",
        text: "Der AI-Agent sendet eine normale HTTP GET-Anfrage. Kein Account, kein API-Key — einfach HTTP wie seit 30 Jahren.",
      },
      {
        phase: "typo3",
        icon: "🔒",
        title: "Middleware prüft den Zugriff",
        text: "Die X402PaywallMiddleware erkennt: Diese Seite hat tx_x402_paywall_enabled = 1. Kein PAYMENT-SIGNATURE Header vorhanden.",
      },
      {
        phase: "response402",
        icon: "←",
        title: "402 Payment Required",
        text: "TYPO3 antwortet mit HTTP 402 und einem PAYMENT-REQUIRED Header. Darin: Preis (0.001 USDC), Netzwerk (Base Mainnet), Wallet-Adresse des Empfängers — alles maschinenlesbar.",
      },
    ],
    result: "402",
    resultText: "Kein Zugriff",
    resultColor: C.error,
  },
  {
    id: "with-payment",
    label: "2 — Mit Zahlung",
    badgeColor: C.success,
    curl: `GET /premium-artikel HTTP/2
Host: typo3.example.at
User-Agent: AI-Agent/1.0
Accept: application/json
PAYMENT-SIGNATURE: eyJmcm9tIjoiMHhBZ2Vu
  dFdhbGxldC4uLiIsInNpZ25hdHVyZ...`,
    response: `HTTP/2 200 OK
Content-Type: text/html; charset=utf-8
PAYMENT-RESPONSE: eyJ0eFdhc2giOiIweGRlY...

<!DOCTYPE html>
<html lang="de">
  <!-- ✅ Vollständiger Artikel-Inhalt -->
  <!-- Transaction: 0xdeadbeef...1234 -->
  <title>TYPO3 x402 — vollständiger Inhalt</title>
  ...
</html>`,
    steps: [
      {
        phase: "client",
        icon: "🔑",
        title: "Anfrage mit Zahlungssignatur",
        text: "Der Agent hat die 402-Antwort gelesen, 0.001 USDC auf Base signiert und sendet die Anfrage erneut — mit PAYMENT-SIGNATURE Header.",
      },
      {
        phase: "facilitator",
        icon: "🌐",
        title: "Weitergabe an Facilitator",
        text: "TYPO3 leitet die Signatur an den Coinbase x402-Facilitator (x402.org) weiter. Dieser überprüft die Blockchain-Transaktion on-chain.",
      },
      {
        phase: "facilitator-ok",
        icon: "✓",
        title: "Facilitator bestätigt",
        text: "Zahlung ist gültig — 0.001 USDC wurden von der Agent-Wallet an Ihre Wallet übertragen und on-chain bestätigt.",
      },
      {
        phase: "response200",
        icon: "←",
        title: "200 OK — Content geliefert",
        text: "TYPO3 liefert den vollständigen Artikel. Im PAYMENT-RESPONSE Header steckt der Transaction-Hash für Ihre Buchhaltung.",
      },
    ],
    result: "200",
    resultText: "Zugriff gewährt",
    resultColor: C.success,
  },
  {
    id: "invalid-sig",
    label: "3 — Ungültige Signatur",
    badgeColor: C.warning,
    curl: `GET /premium-artikel HTTP/2
Host: typo3.example.at
User-Agent: Evil-Bot/1.0
Accept: application/json
PAYMENT-SIGNATURE: UNGUELTIGE_SIGNATUR_BASE64`,
    response: `HTTP/2 402 Payment Required
Content-Type: application/json
PAYMENT-REQUIRED: eyJzY2hlbWUiOiJleGFjdCIs...

{
  "status": 402,
  "error": "Payment verification failed",
  "message": "Invalid signature: transaction
              not found on chain"
}`,
    steps: [
      {
        phase: "client",
        icon: "⚠️",
        title: "Gefälschte Signatur",
        text: "Ein Agent (oder Angreifer) sendet eine ungültige PAYMENT-SIGNATURE — gefälscht, veraltet oder für die falsche Anfrage signiert.",
      },
      {
        phase: "facilitator",
        icon: "🌐",
        title: "Prüfung beim Facilitator",
        text: "TYPO3 leitet die Signatur trotzdem zur Überprüfung weiter. Der Facilitator schaut auf der Blockchain nach.",
      },
      {
        phase: "facilitator-reject",
        icon: "✗",
        title: "Facilitator lehnt ab",
        text: "Keine passende Transaktion gefunden. Kein Geld wurde übertragen — der Versuch kostet dem Angreifer nichts, bringt aber auch nichts.",
      },
      {
        phase: "response402",
        icon: "←",
        title: "Erneut 402 mit Fehler",
        text: "TYPO3 antwortet wieder mit 402 und einer Fehlermeldung. Der Content bleibt geschützt.",
      },
    ],
    result: "402",
    resultText: "Abgelehnt",
    resultColor: C.warning,
  },
];

// ── Component ─────────────────────────────────────────────────────────────────
export function X402BlogSimulator() {
  const [activeScenario, setActiveScenario] = useState(0);
  const [activeStep, setActiveStep] = useState(-1);
  const [isRunning, setIsRunning] = useState(false);
  const [showResponse, setShowResponse] = useState(false);
  const [typedCurl, setTypedCurl] = useState("");
  const [typedResponse, setTypedResponse] = useState("");

  const flowRef = useRef<HTMLDivElement>(null);
  const arrowCTRef = useRef<HTMLDivElement>(null);
  const arrowTFRef = useRef<HTMLDivElement>(null);
  const nodeClientRef = useRef<HTMLDivElement>(null);
  const nodeTypo3Ref = useRef<HTMLDivElement>(null);
  const nodeFacilitatorRef = useRef<HTMLDivElement>(null);
  const gsapRef = useRef<typeof import("gsap")["gsap"] | null>(null);
  const tlRef = useRef<ReturnType<typeof import("gsap")["gsap"]["timeline"]> | null>(null);

  const scenario = SCENARIOS[activeScenario];

  // Load GSAP dynamically
  useEffect(() => {
    import("gsap").then((mod) => {
      gsapRef.current = mod.gsap;
    }).catch(() => {
      // GSAP not available — CSS fallback will handle animation
    });
  }, []);

  // Typewriter helper
  const typeText = useCallback((
    text: string,
    setter: (v: string) => void,
    speed = 8
  ): Promise<void> => {
    return new Promise((resolve) => {
      let i = 0;
      setter("");
      const interval = setInterval(() => {
        setter(text.slice(0, i));
        i += speed;
        if (i > text.length) {
          setter(text);
          clearInterval(interval);
          resolve();
        }
      }, 16);
    });
  }, []);

  // Node glow animation
  const glowNode = useCallback((nodeRef: React.RefObject<HTMLDivElement | null>, color: string) => {
    const el = nodeRef.current;
    if (!el) return;
    el.style.transition = "box-shadow 0.3s, border-color 0.3s";
    el.style.boxShadow = `0 0 20px ${color}88, 0 0 40px ${color}44`;
    el.style.borderColor = color;
  }, []);

  const resetNodes = useCallback(() => {
    [nodeClientRef, nodeTypo3Ref, nodeFacilitatorRef].forEach((ref) => {
      if (ref.current) {
        ref.current.style.boxShadow = "";
        ref.current.style.borderColor = C.primaryDark;
      }
    });
  }, []);

  // Run simulation
  const runSimulation = useCallback(async () => {
    if (isRunning) return;
    setIsRunning(true);
    setActiveStep(-1);
    setShowResponse(false);
    setTypedCurl("");
    setTypedResponse("");
    resetNodes();

    const steps = scenario.steps;

    // Type out the curl command first
    await typeText(scenario.curl, setTypedCurl, 6);
    await sleep(300);

    for (let i = 0; i < steps.length; i++) {
      setActiveStep(i);
      const step = steps[i];

      switch (step.phase) {
        case "client":
          glowNode(nodeClientRef, C.primaryLight);
          await sleep(600);
          glowNode(nodeTypo3Ref, C.primary);
          break;
        case "typo3":
          glowNode(nodeTypo3Ref, C.warning);
          break;
        case "facilitator":
          glowNode(nodeTypo3Ref, C.primary);
          await sleep(300);
          glowNode(nodeFacilitatorRef, C.primaryLight);
          break;
        case "facilitator-ok":
          glowNode(nodeFacilitatorRef, C.success);
          break;
        case "facilitator-reject":
          glowNode(nodeFacilitatorRef, C.error);
          break;
        case "response200":
          glowNode(nodeTypo3Ref, C.success);
          await sleep(300);
          glowNode(nodeClientRef, C.success);
          break;
        case "response402":
          glowNode(nodeTypo3Ref, C.error);
          await sleep(300);
          glowNode(nodeClientRef, C.error);
          break;
      }

      await sleep(1400);
    }

    // Show response with typewriter
    setShowResponse(true);
    await sleep(200);
    await typeText(scenario.response, setTypedResponse, 10);

    setIsRunning(false);
  }, [isRunning, scenario, typeText, glowNode, resetNodes]);

  // Reset when scenario changes
  useEffect(() => {
    setActiveStep(-1);
    setShowResponse(false);
    setTypedCurl("");
    setTypedResponse("");
    setIsRunning(false);
    resetNodes();
  }, [activeScenario, resetNodes]);

  const currentStep = activeStep >= 0 ? scenario.steps[activeStep] : null;

  return (
    <div
      style={{
        background: `linear-gradient(135deg, ${C.dark} 0%, ${C.dark2} 100%)`,
        borderRadius: "16px",
        padding: "32px 24px",
        margin: "40px 0",
        fontFamily: "'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif",
        border: `1px solid ${C.dark3}`,
        overflow: "hidden",
        position: "relative",
      }}
    >
      {/* Decorative background teal glow */}
      <div style={{
        position: "absolute", top: "-60px", right: "-60px", width: "200px", height: "200px",
        background: `radial-gradient(circle, ${C.primary}22 0%, transparent 70%)`,
        pointerEvents: "none",
      }} />

      {/* Header */}
      <div style={{ marginBottom: "24px" }}>
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "8px" }}>
          <span style={{
            background: C.primary, color: "#fff", borderRadius: "6px",
            padding: "3px 10px", fontSize: "11px", fontWeight: 700, letterSpacing: "1px",
            textTransform: "uppercase"
          }}>
            Interaktive Demo
          </span>
          <span style={{ color: C.muted, fontSize: "12px" }}>x402 Payment Protocol</span>
        </div>
        <h3 style={{ color: C.text, fontSize: "20px", fontWeight: 700, margin: 0 }}>
          Wie funktioniert x402? — Live-Simulation
        </h3>
        <p style={{ color: C.muted, fontSize: "14px", marginTop: "6px", marginBottom: 0, lineHeight: "1.5" }}>
          Wählen Sie ein Szenario und klicken Sie <strong style={{ color: C.primaryLight }}>▶ Simulieren</strong>.
          Die Animation zeigt, was Schritt für Schritt im HTTP-Protokoll passiert.
        </p>
      </div>

      {/* Scenario tabs */}
      <div style={{ display: "flex", gap: "8px", marginBottom: "20px", flexWrap: "wrap" }}>
        {SCENARIOS.map((s, i) => (
          <button
            key={s.id}
            onClick={() => setActiveScenario(i)}
            style={{
              background: i === activeScenario ? C.primary : C.dark3,
              color: i === activeScenario ? "#fff" : C.muted,
              border: `1px solid ${i === activeScenario ? C.primary : C.dark3}`,
              borderRadius: "8px", padding: "7px 14px", fontSize: "13px", fontWeight: 600,
              cursor: "pointer", transition: "all 0.2s",
            }}
          >
            <span style={{ marginRight: "6px" }}>{s.badgeColor === C.success ? "✅" : s.badgeColor === C.error ? "❌" : "⚠️"}</span>
            {s.label}
          </button>
        ))}
      </div>

      {/* Flow diagram */}
      <div ref={flowRef} style={{
        background: `${C.dark3}88`, borderRadius: "12px", padding: "24px 16px",
        marginBottom: "20px", border: `1px solid ${C.dark3}`,
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: "0", justifyContent: "space-between" }}>

          {/* Client node */}
          <FlowNode
            nodeRef={nodeClientRef}
            icon="🤖"
            label="Requester"
            name="AI-Agent / Client"
          />

          {/* Arrow: Client → TYPO3 */}
          <FlowArrow
            label={scenario.id === "with-payment" ? "GET + Signatur →" : "GET (keine Zahlung) →"}
            returnLabel={scenario.result === "200" ? "← 200 OK" : "← 402 Required"}
            returnColor={scenario.result === "200" ? C.success : C.error}
            active={activeStep >= 0}
            step={activeStep}
            maxStep={2}
          />

          {/* TYPO3 node */}
          <FlowNode
            nodeRef={nodeTypo3Ref}
            icon="🔒"
            label="Server"
            name="TYPO3 Paywall"
          />

          {/* Arrow: TYPO3 ↔ Facilitator */}
          <FlowArrow
            label="→ Signatur prüfen"
            returnLabel={scenario.id === "invalid-sig" ? "← Abgelehnt" : "← Bestätigt"}
            returnColor={scenario.id === "invalid-sig" ? C.error : C.success}
            active={scenario.steps.some(s => s.phase === "facilitator") && activeStep >= scenario.steps.findIndex(s => s.phase === "facilitator")}
            dim={!scenario.steps.some(s => s.phase === "facilitator")}
            step={activeStep}
            maxStep={scenario.steps.length - 1}
          />

          {/* Facilitator node */}
          <FlowNode
            nodeRef={nodeFacilitatorRef}
            icon="🌐"
            label="Coinbase"
            name="x402 Facilitator"
            dim={!scenario.steps.some(s => s.phase === "facilitator")}
          />
        </div>
      </div>

      {/* Step explanation */}
      <div style={{
        minHeight: "70px", marginBottom: "20px",
        background: currentStep ? `${C.dark3}cc` : "transparent",
        borderRadius: "10px", padding: currentStep ? "14px 18px" : "0",
        border: currentStep ? `1px solid ${C.primary}44` : "none",
        transition: "all 0.3s",
      }}>
        {currentStep && (
          <>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "6px" }}>
              <span style={{ fontSize: "18px" }}>{currentStep.icon}</span>
              <span style={{ color: C.primaryLight, fontWeight: 700, fontSize: "14px" }}>
                Schritt {activeStep + 1}/{scenario.steps.length}: {currentStep.title}
              </span>
            </div>
            <p style={{ color: C.text, fontSize: "13px", lineHeight: "1.6", margin: 0 }}>
              {currentStep.text}
            </p>
          </>
        )}
        {!currentStep && (
          <p style={{ color: C.muted, fontSize: "13px", margin: 0, padding: "14px 0" }}>
            Klicken Sie auf <strong style={{ color: C.primaryLight }}>▶ Simulieren</strong>, um die x402-Kommunikation Schritt für Schritt zu sehen.
          </p>
        )}
      </div>

      {/* Request / Response panels */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "16px" }}>
        {/* Request */}
        <div style={{ background: C.dark, borderRadius: "10px", overflow: "hidden", border: `1px solid ${C.dark3}` }}>
          <div style={{
            background: C.dark3, padding: "8px 14px", display: "flex",
            alignItems: "center", gap: "8px"
          }}>
            <span style={{ color: C.primaryLight, fontSize: "11px", fontWeight: 700, letterSpacing: "1px", textTransform: "uppercase" }}>
              📤 HTTP-Anfrage
            </span>
          </div>
          <pre style={{
            margin: 0, padding: "14px", fontSize: "11px", lineHeight: "1.6",
            color: C.muted, whiteSpace: "pre-wrap", wordBreak: "break-all",
            minHeight: "120px", fontFamily: "monospace",
          }}>
            <span style={{ color: C.primaryLight }}>{typedCurl || <span style={{ opacity: 0.4 }}>Anfrage erscheint hier...</span>}</span>
          </pre>
        </div>

        {/* Response */}
        <div style={{ background: C.dark, borderRadius: "10px", overflow: "hidden", border: `1px solid ${C.dark3}` }}>
          <div style={{
            background: C.dark3, padding: "8px 14px", display: "flex",
            alignItems: "center", justifyContent: "space-between"
          }}>
            <span style={{ color: C.primaryLight, fontSize: "11px", fontWeight: 700, letterSpacing: "1px", textTransform: "uppercase" }}>
              📥 HTTP-Antwort
            </span>
            {showResponse && (
              <span style={{
                background: scenario.resultColor, color: "#fff",
                borderRadius: "12px", padding: "2px 10px", fontSize: "12px", fontWeight: 700,
              }}>
                {scenario.result} {scenario.resultText}
              </span>
            )}
          </div>
          <pre style={{
            margin: 0, padding: "14px", fontSize: "11px", lineHeight: "1.6",
            color: showResponse ? (scenario.result === "200" ? "#86efac" : "#fca5a5") : C.muted,
            whiteSpace: "pre-wrap", wordBreak: "break-all", minHeight: "120px",
            fontFamily: "monospace",
          }}>
            {typedResponse || <span style={{ opacity: 0.4 }}>Antwort erscheint nach Simulation...</span>}
          </pre>
        </div>
      </div>

      {/* Run button + progress */}
      <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
        <button
          onClick={runSimulation}
          disabled={isRunning}
          style={{
            background: isRunning ? C.dark3 : C.primary,
            color: "#fff", border: "none", borderRadius: "8px",
            padding: "10px 24px", fontSize: "14px", fontWeight: 700,
            cursor: isRunning ? "not-allowed" : "pointer",
            opacity: isRunning ? 0.7 : 1,
            transition: "all 0.2s", letterSpacing: "0.5px",
          }}
        >
          {isRunning ? "⏳ Simulation läuft..." : "▶ Simulieren"}
        </button>

        {activeStep >= 0 && (
          <div style={{ display: "flex", gap: "6px", alignItems: "center" }}>
            {scenario.steps.map((_, i) => (
              <div
                key={i}
                style={{
                  width: i <= activeStep ? "24px" : "8px",
                  height: "8px", borderRadius: "4px",
                  background: i < activeStep ? C.success : i === activeStep ? C.primary : C.dark3,
                  transition: "all 0.3s",
                }}
              />
            ))}
          </div>
        )}
      </div>

      {/* Footer note */}
      <p style={{
        color: C.muted, fontSize: "11px", marginTop: "16px", marginBottom: 0,
        borderTop: `1px solid ${C.dark3}`, paddingTop: "12px",
      }}>
        Diese Simulation läuft vollständig im Browser. Für echte Tests mit Base Sepolia Testnet-USDC (kostenlos):
        {" "}<a href="https://faucet.circle.com" target="_blank" rel="noopener noreferrer" style={{ color: C.primaryLight }}>faucet.circle.com</a>
        {" "}· <a href="https://github.com/dirnbauer/typo3-x402-paywall" target="_blank" rel="noopener noreferrer" style={{ color: C.primaryLight }}>GitHub</a>
      </p>
    </div>
  );
}

// ── Sub-components ────────────────────────────────────────────────────────────

function FlowNode({
  nodeRef, icon, label, name, dim = false,
}: {
  nodeRef: React.RefObject<HTMLDivElement | null>;
  icon: string; label: string; name: string; dim?: boolean;
}) {
  return (
    <div
      ref={nodeRef}
      style={{
        background: dim ? C.dark3 : C.dark2,
        border: `2px solid ${dim ? C.dark3 : C.primaryDark}`,
        borderRadius: "10px", padding: "14px 16px", textAlign: "center",
        minWidth: "110px", transition: "box-shadow 0.3s, border-color 0.3s",
        opacity: dim ? 0.4 : 1,
      }}
    >
      <div style={{ fontSize: "24px", marginBottom: "4px" }}>{icon}</div>
      <div style={{ color: C.muted, fontSize: "10px", letterSpacing: "1px", textTransform: "uppercase" }}>{label}</div>
      <div style={{ color: C.text, fontSize: "12px", fontWeight: 600, marginTop: "2px" }}>{name}</div>
    </div>
  );
}

function FlowArrow({
  label, returnLabel, returnColor, active, dim = false, step, maxStep,
}: {
  label: string; returnLabel: string; returnColor: string;
  active: boolean; dim?: boolean; step: number; maxStep: number;
}) {
  return (
    <div style={{
      flex: 1, display: "flex", flexDirection: "column", alignItems: "center",
      gap: "6px", padding: "0 4px", opacity: dim ? 0.3 : 1, position: "relative",
    }}>
      <div style={{ fontSize: "10px", color: active ? C.primaryLight : C.muted, transition: "color 0.3s", textAlign: "center", whiteSpace: "nowrap" }}>
        {label}
      </div>
      <div style={{
        width: "100%", height: "2px",
        background: active
          ? `linear-gradient(90deg, ${C.primary}, ${C.primaryLight})`
          : C.dark3,
        position: "relative", transition: "background 0.5s",
      }}>
        <div style={{
          position: "absolute", right: "-4px", top: "-4px",
          color: active ? C.primaryLight : C.dark3,
          fontSize: "10px", transition: "color 0.3s",
        }}>▶</div>
      </div>
      <div style={{
        width: "100%", height: "2px",
        background: step >= maxStep
          ? `linear-gradient(90deg, ${returnColor}, ${returnColor}88)`
          : C.dark3,
        position: "relative", transition: "background 0.5s",
      }}>
        <div style={{
          position: "absolute", left: "-4px", top: "-4px",
          color: step >= maxStep ? returnColor : C.dark3,
          fontSize: "10px", transition: "color 0.3s",
        }}>◀</div>
      </div>
      <div style={{
        fontSize: "10px", color: step >= maxStep ? returnColor : C.muted,
        transition: "color 0.3s", textAlign: "center", whiteSpace: "nowrap",
      }}>
        {returnLabel}
      </div>
    </div>
  );
}

function sleep(ms: number) {
  return new Promise<void>((r) => setTimeout(r, ms));
}

export default X402BlogSimulator;
