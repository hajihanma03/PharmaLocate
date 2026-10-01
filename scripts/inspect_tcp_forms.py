from pypdf import PdfReader
from pathlib import Path

paths = [
    r"c:\Users\ADMIN\Downloads\CCS TCP FORM4 rev 2(progress report-tech ad).pdf",
    r"c:\Users\ADMIN\Downloads\CCS TCP FORM5-rev2(progress report-subject teacher).pdf",
]
for p in paths:
    print("=" * 60, Path(p).name)
    r = PdfReader(p)
    print("pages", len(r.pages), "encrypted", r.is_encrypted)
    print("meta", r.metadata)
    print("form", r.get_fields())
    for i, page in enumerate(r.pages):
        print(f"--- page {i+1} mediabox", float(page.mediabox.width), float(page.mediabox.height))
        annots = page.get("/Annots")
        print("annots", type(annots), annots)
        text = page.extract_text() or ""
        out = Path(p).with_suffix(".extract.txt")
        chunks = []
        for i, page in enumerate(r.pages):
            chunks.append(f"\n===== PAGE {i+1} =====\n")
            chunks.append(page.extract_text() or "")
        out.write_text("".join(chunks), encoding="utf-8")
        print("wrote", out, "chars", sum(len(c) for c in chunks))
