import puppeteer from "puppeteer";
import path from "node:path";
import fs from "node:fs";

const url = process.argv[2] || "http://localhost:3002";
const label = process.argv[3];
const width = Number(process.argv[4] || 1600);
const height = Number(process.argv[5] || 1000);
const fullPage = process.argv[6] !== "viewport";

const dir = path.join(process.cwd(), "temporary screenshots");
fs.mkdirSync(dir, { recursive: true });

let n = 1;
while (fs.existsSync(path.join(dir, `screenshot-${n}${label ? "-" + label : ""}.png`))) n++;
const outFile = path.join(dir, `screenshot-${n}${label ? "-" + label : ""}.png`);

const browser = await puppeteer.launch({ headless: "new" });
const page = await browser.newPage();
await page.setViewport({ width, height });
await page.goto(url, { waitUntil: "networkidle0", timeout: 30000 });
await new Promise((r) => setTimeout(r, 1400));

await page.screenshot({ path: outFile, fullPage });
await browser.close();

console.log(`Saved ${outFile}`);
