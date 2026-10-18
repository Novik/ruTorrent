import { readFileSync } from "fs";

// The WebUI posts several forms into hidden frames: add a torrent by file
// or by URL, the create, source, screenshots and spectrogram dialogs, and
// the mobile plugin's add dialog, which it loads into this same document.
// A form POST is not a CORS request: under a no-referrer policy, which a
// proxy header or a browser setting can impose, the browser sends
// "Origin: null" and no Referer, and Requests::makeCSRFCheck() cannot tell
// that from a foreign page. index.html therefore states its own policy
// before anything on it can submit a form.
describe("index.html referrer policy", () => {
  const doc = new DOMParser().parseFromString(
    readFileSync("../index.html", "utf8"),
    "text/html",
  );
  const policies = [...doc.querySelectorAll("meta[name]")].filter(
    (meta) => meta.getAttribute("name").trim().toLowerCase() === "referrer",
  );

  test("is declared once, as same-origin, in the head", () => {
    expect(policies).toHaveLength(1);
    expect(policies[0].parentNode).toBe(doc.head);
    expect(policies[0].getAttribute("content")).toBe("same-origin");
  });

  test("comes before every script and form", () => {
    expect(policies).toHaveLength(1);
    const later = [...doc.querySelectorAll("script, form")];
    expect(later.length).toBeGreaterThan(0);
    for (const element of later) {
      expect(
        policies[0].compareDocumentPosition(element) &
          Node.DOCUMENT_POSITION_FOLLOWING,
      ).toBeTruthy();
    }
  });
});
