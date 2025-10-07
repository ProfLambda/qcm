import asyncio
from playwright.async_api import async_playwright, expect
import pathlib

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()

        # Obtenir le chemin absolu du répertoire de travail actuel
        project_root = pathlib.Path.cwd()

        # 1. Vérifier la page d'accueil (index.php)
        index_path = project_root / 'public' / 'index.php'
        await page.goto(f"file://{index_path}")
        await page.wait_for_timeout(1000) # Laisser le temps au JS de s'exécuter
        await page.screenshot(path="jules-scratch/verification/01_index.png")

        # 2. Vérifier la page de connexion (login.php)
        login_path = project_root / 'public' / 'login.php'
        await page.goto(f"file://{login_path}")
        await page.wait_for_timeout(500)
        await page.screenshot(path="jules-scratch/verification/02_login.png")

        # 3. Vérifier la page d'inscription (signup.php)
        signup_path = project_root / 'public' / 'signup.php'
        await page.goto(f"file://{signup_path}")
        await page.wait_for_timeout(500)
        await page.screenshot(path="jules-scratch/verification/03_signup.png")

        # 4. Vérifier la page du quiz runner (quiz.php)
        # On s'attend à voir l'état de chargement ou une erreur car les données PHP manquent
        quiz_path = project_root / 'public' / 'quiz.php'
        await page.goto(f"file://{quiz_path}")
        await page.wait_for_timeout(1000) # Laisser le temps au JS de s'exécuter
        await page.screenshot(path="jules-scratch/verification/04_quiz_runner.png")

        await browser.close()

if __name__ == '__main__':
    # Correction pour l'exécution asynchrone
    # Playwright's sync_api can be tricky in some environments,
    # so we use the async API with asyncio.run() for robustness.
    try:
        loop = asyncio.get_running_loop()
    except RuntimeError:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)

    loop.run_until_complete(main())