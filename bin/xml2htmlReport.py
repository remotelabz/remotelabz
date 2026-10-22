#!/usr/bin/env python3
"""Convertit un rapport PHPUnit JUnit XML en page HTML détaillée.

Usage :
    python3 bin/junit_to_html.py var/report/junit.xml var/report/report.html
"""

import html
import sys
import xml.etree.ElementTree as ET
from datetime import datetime

CSS = """
body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; }
h1 { font-size: 1.5rem; }
.summary { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.stat { padding: 0.75rem 1.25rem; border-radius: 8px; background: #f3f3f5; }
.stat b { display: block; font-size: 1.4rem; }
.ok    { background: #e6f6ea; color: #17632f; }
.fail  { background: #fdeaea; color: #a12622; }
.skip  { background: #fff6dd; color: #8a6400; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 0.5rem 0.75rem; text-align: left; vertical-align: top; }
th { background: #f7f7f9; cursor: pointer; position: sticky; top: 0; }
tr.failed td.status { background: #fdeaea; color: #a12622; font-weight: bold; }
tr.error  td.status { background: #fdeaea; color: #a12622; font-weight: bold; }
tr.passed td.status { background: #e6f6ea; color: #17632f; }
tr.skipped td.status { background: #fff6dd; color: #8a6400; }
.class-name { font-weight: 600; background: #fafafa; }
details pre { background: #111; color: #eee; padding: 1rem; border-radius: 6px;
              overflow-x: auto; font-size: 0.85rem; white-space: pre-wrap; }
details { margin-top: 0.4rem; }
summary { cursor: pointer; color: #555; font-size: 0.9rem; }
"""


def esc(text):
    return html.escape(text or "")


def collect(root):
    """Parcourt le XML et retourne la liste des tests + les stats globales."""
    stats = {"tests": 0, "failures": 0, "errors": 0, "skipped": 0, "time": 0.0}
    cases = []
    for tc in root.iter("testcase"):
        stats["tests"] += 1
        stats["time"] += float(tc.get("time", 0))
        cls = tc.get("class") or tc.get("classname") or "(sans classe)"
        name = tc.get("name", "?")
        failure = tc.find("failure")
        error = tc.find("error")
        if failure is not None:
            stats["failures"] += 1
            status, typ, msg = "Échec", failure.get("type", ""), failure.text or ""
        elif error is not None:
            stats["errors"] += 1
            status, typ, msg = "Erreur", error.get("type", ""), error.text or ""
        elif tc.find("skipped") is not None:
            stats["skipped"] += 1
            status, typ, msg = "Ignoré", "", (tc.find("skipped").text or "")
        else:
            status, typ, msg = "OK", "", ""
        cases.append({
            "class": cls,
            "name": name,
            "status": status,
            "type": typ,
            "message": msg.strip(),
            "time": float(tc.get("time", 0)),
        })
    return cases, stats


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    xml_path = sys.argv[1]
    html_path = sys.argv[2] if len(sys.argv) > 2 else "var/report/report.html"

    tree = ET.parse(xml_path)
    cases, stats = collect(tree.getroot())

    passed = stats["tests"] - stats["failures"] - stats["errors"] - stats["skipped"]

    rows = []
    current_class = None
    for case in sorted(cases, key=lambda c: (c["class"], c["name"])):
        if case["class"] != current_class:
            current_class = case["class"]
            rows.append(
                f'<tr class="class-name"><td colspan="4">{esc(current_class)}</td></tr>'
            )
        row_status = {"OK": "passed", "Échec": "failed",
                      "Erreur": "error", "Ignoré": "skipped"}[case["status"]]
        detail = ""
        if case["message"]:
            detail = (
                '<details><summary>Afficher le détail de l\'échec '
                f'({esc(case["type"])})</summary>'
                f"<pre>{esc(case['message'])}</pre></details>"
            )
        rows.append(
            f'<tr class="{row_status}">'
            f'<td class="status">{case["status"]}</td>'
            f"<td>{esc(case['name'])}</td>"
            f'<td>{case["time"]:.3f}s{detail}</td>'
            f'<td class="typecell">{esc(case["type"])}</td>'
            "</tr>"
        )

    document = f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport de tests PHPUnit</title>
<style>{CSS}</style>
</head>
<body>
<h1>Rapport de tests PHPUnit</h1>
<p>Généré le {datetime.now().strftime('%d/%m/%Y à %H:%M')} depuis <code>{esc(xml_path)}</code></p>
<div class="summary">
  <div class="stat"><b>{stats['tests']}</b>tests</div>
  <div class="stat ok"><b>{passed}</b>réussis</div>
  <div class="stat fail"><b>{stats['failures']}</b>échecs</div>
  <div class="stat fail"><b>{stats['errors']}</b>erreurs</div>
  <div class="stat skip"><b>{stats['skipped']}</b>ignorés</div>
  <div class="stat"><b>{stats['time']:.2f}s</b>durée totale</div>
</div>
<table>
<thead><tr>
<th>Statut</th><th>Test</th><th>Durée</th><th>Type d'exception</th>
</tr></thead>
<tbody>
{''.join(rows)}
</tbody>
</table>
</body>
</html>
"""

    with open(html_path, "w", encoding="utf-8") as f:
        f.write(document)

    print(f"Rapport écrit : {html_path}")
    print(f"Tests : {stats['tests']} | Échecs : {stats['failures']} "
          f"| Erreurs : {stats['errors']} | Ignorés : {stats['skipped']}")


if __name__ == "__main__":
    main()
