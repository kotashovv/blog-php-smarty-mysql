const fs = require("node:fs");
const families = [
  ["Manrope", "manrope", "200..800"],
  ["Unbounded", "unbounded", "200..900"],
  ["Source Serif 4", "source-serif-4", "200..900"],
  ["Space Mono", "space-mono", "400"],
];
(async () => {
  fs.mkdirSync("public/assets/fonts", { recursive: true });
  for (const [family, name, weight] of families) {
    const url = `https://fonts.googleapis.com/css2?family=${family.replaceAll(" ", "+")}:wght@${weight}&display=swap`;
    const response = await fetch(url, {
      headers: {
        "User-Agent":
          "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
      },
    });
    if (!response.ok) throw Error(`Font CSS ${response.status}`);
    const css = await response.text();
    for (const subset of name === "space-mono"
      ? ["latin"]
      : ["latin", "cyrillic"]) {
      const block = css.match(
        new RegExp("/\\* " + subset + " \\*/([\\s\\S]*?)(?=/\\*|$)"),
      )?.[1];
      const source = block?.match(/url\(([^)]+\.woff2)\)/)?.[1];
      if (!source) throw Error(`No ${subset} WOFF2 for ${family}: ${css}`);
      const file = await fetch(source);
      if (!file.ok) throw Error(`Font download ${file.status}`);
      fs.writeFileSync(
        `public/assets/fonts/${name}-${subset}.woff2`,
        Buffer.from(await file.arrayBuffer()),
      );
    }
    const repoName =
      name === "source-serif-4" ? "sourceserif4" : name.replaceAll("-", "");
    const license = await fetch(
      `https://raw.githubusercontent.com/google/fonts/main/ofl/${repoName}/OFL.txt`,
    );
    if (!license.ok) throw Error(`License ${license.status} for ${family}`);
    fs.writeFileSync(
      `public/assets/fonts/${name}-OFL.txt`,
      await license.text(),
    );
  }
  console.log("Local fonts and licenses downloaded.");
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
