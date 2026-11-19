"""Download Distileriile Bran images and write site/bran/products.js.
Usage: fetch products.json + p2.json from https://www.distileriilebran.ro/en/wp-json/wc/store/v1/products?per_page=50(&page=2)
into a folder, then: python3 scripts/bran-fetch-assets.py <that folder> site/bran"""
import html, io, json, re, sys, urllib.request
from pathlib import Path
from PIL import Image

SRC = Path(sys.argv[1])          # dir holding products.json + p2.json
OUT = Path(sys.argv[2])          # site/bran
IMG = OUT / "img"
IMG.mkdir(parents=True, exist_ok=True)

def fetch(url):
    with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=60) as r:
        return r.read()

def save(url, name, max_side, quality=80):
    dest = IMG / name
    if dest.exists():
        return dest.name
    im = Image.open(io.BytesIO(fetch(url)))
    im = im.convert("RGBA") if im.mode in ("RGBA", "LA", "P") else im.convert("RGB")
    im.thumbnail((max_side, max_side), Image.LANCZOS)
    im.save(dest, "WEBP", quality=quality, method=6)
    return dest.name

# Brand / decorative images
U = "https://www.distileriilebran.ro/wp-content/uploads/"
brand = {
    "logo-white.webp": (U + "2026/11/Bran-logo-alb-300.webp", 300),
    "logo-brown.webp": (U + "2026/11/Bran-logo-maro-1-300.webp", 300),
    "sun.webp": (U + "2026/07/sun-scaled-2.webp", 1100),
    "moon.webp": (U + "2025/10/luna.png", 700),
    "bats.webp": (U + "2025/10/bat7.png", 900),
    "bat.webp": (U + "2025/10/bat1.png", 500),
    "birds.webp": (U + "2025/10/pasare2.png", 900),
    "still.webp": (U + "2025/10/alambic-scaled.png", 1400),
    "still-steam.webp": (U + "2025/10/Abure-alambic-scaled.png", 1100),
    "ornament.webp": (U + "2025/10/element.png", 1400),
}
for name, (url, size) in brand.items():
    save(url, name, size)
    print("brand", name)

ABV = re.compile(r"([\d.]+)\s*%")
ML = re.compile(r"(\d+)\s*ml", re.I)

FLAVOUR = [  # (romanian key, english)
    ("Afinat", "Blueberry"), ("Vișinat", "Sour Cherry"), ("Caisat", "Apricot"),
    ("Căpșunat", "Strawberry"), ("Mure", "Blackberry"), ("Nuci Verzi", "Green Walnut"),
    ("Ienupăr", "Juniper"),
]
FRUIT = {"Prune": "Plum", "Pere": "Pear", "Gutui": "Quince", "Caise": "Apricot", "Mere": "Apple"}

COLLECTION_EN = {
    "Distileriile Bran": "Distileriile Bran",
    "Casa Pălincii": "Casa Pălincii",
    "Vampire’s Heart": "Vampire’s Heart",
    "Vampire’s Fears": "Vampire’s Fears",
    "Vampire’s Tears": "Vampire’s Tears",
    "Vampire’s Prince": "Vampire’s Prince",
    "Vampire’s Nightlife": "Vampire’s Nightlife",
    "Vampire’s Seduction Sangria": "Vampire’s Seduction",
    "The Witch": "The Witch",
    "Fangtastic": "Fangtastic",
}

def english_name(raw):
    n = raw
    if n.startswith("FANGTASTIC"):
        return "Fangtastic Choco Pralines", "Chocolate"
    if n.startswith("THE WITCH"):
        for k, e in FLAVOUR + [("căpșuni", "Strawberry"), ("afine", "Blueberry"), ("vișine", "Sour Cherry"), ("caise", "Apricot")]:
            if k.lower() in n.lower():
                return f"The Witch Pralines · {e} Liqueur", "Chocolate"
    if n.startswith("Bransylvania"):
        kind = {"Tenebris": "Fetească Neagră dry red", "Rosa": "Rosé", "Aura": "White"}
        for k, v in kind.items():
            if k in n:
                return f"Bransylvania {k}", "Wine"
    if "Sangria" in n:
        return "Vampire’s Seduction Sangria", "Wine"
    if "Nightlife" in n:
        return "Vampire’s Nightlife Sweet Red", "Wine"
    if n.startswith("Set"):
        inner = re.search(r"\(([^)]*)\)", n).group(1)
        count = re.match(r"Set (\d)", n).group(1)
        parts = [p.strip() for p in re.split(r"[+,]", inner)]
        tr = []
        for p in parts:
            hit = next((e for k, e in FLAVOUR if k.lower() in p.lower()), None) or FRUIT.get(p)
            tr.append(hit or p)
        if "Vampire" in n:
            label = "Liqueurs" if "Lichior" in n else "Pălincă"
            return f"Vampire’s Tears Gift Set · {count} {label}", "Gift Set"
        label = "Liqueur" if "Lichior" in n or "Vișinată" in n else "Pălincă"
        return f"Gift Set of {count} · " + ", ".join(tr) + f" {label}", "Gift Set"
    brand = next((b for b in ["Vampire’s Heart", "Vampire’s Fears", "Vampire’s Tears", "Vampire’s Prince",
                              "Casa Pălincii", "Distileriile Bran", "Samaro", "Juniperus"] if b in n), "")
    if "Vodka" in n:
        return f"{brand} Vodka".strip(), "Vodka"
    if "Gin" in n:
        return f"{brand} Gin".strip(), "Gin"
    for k, e in FLAVOUR:
        if k.lower() in n.lower():
            return f"{brand} {e} Liqueur".strip(), "Liqueur"
    for spirit, en in [("Pălincă", "Pălincă"), ("Țuică", "Țuică"), ("Rachiu", "Rachiu")]:
        if spirit in n:
            fruit = next(e for k, e in FRUIT.items() if k in n)
            extra = ""
            if "Ediție Limitată" in n: extra = " · Limited Edition"
            if "+ 2 Toiuri" in n: extra = " · with 2 Țoi glasses"
            if "Cutie" in n: extra = " · Gift Box"
            return f"{brand} {fruit} {en}{extra}".strip(), "Pălincă & Țuică"
    return n, "Other"

raw = json.load(open(SRC / "products.json")) + json.load(open(SRC / "p2.json"))
products = []
for p in raw:
    name = html.unescape(p["name"])
    cats = [html.unescape(c["name"]).replace("'", "’") for c in p["categories"]]
    gift = any("Miniaturi" in c for c in cats)
    coll = next((c for c in cats if "Miniaturi" not in c), "Distileriile Bran")
    if "Bransylvania" in name:
        coll = "Bransylvania"
    en, kind = english_name(name)
    ml = ML.search(name); abv = ABV.search(name)
    if not p["images"]:
        continue
    img = save(p["images"][0]["src"], f"p{p['id']}.webp", 640, 78)
    products.append({
        "id": p["id"], "name": en, "original": name, "collection": COLLECTION_EN.get(coll, coll),
        "type": kind, "gift": gift or kind == "Gift Set" or (ml and int(ml.group(1)) <= 50),
        "ml": int(ml.group(1)) if ml else None,
        "abv": float(abv.group(1)) if abv and kind not in ("Chocolate",) else None,
        "price": int(p["prices"]["price"]) / 100, "img": "img/" + img,
    })
    print("product", p["id"], en)

(OUT / "products.js").write_text("window.PRODUCTS = " + json.dumps(products, ensure_ascii=False, indent=0) + ";\n")
print(len(products), "products")
