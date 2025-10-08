import sqlite3
import time
import os
from playwright.sync_api import sync_playwright, expect

# --- Test Configuration ---
BASE_URL = "http://localhost:8000"
DB_PATH = "public/data/app.db"
USER_EMAIL = "admin@test.com"
USER_PASSWORD = "password123"
SCREENSHOT_PATH = "verification.png"

def run_verification():
    """
    Main function to run the verification steps.
    """
    # Clean up old database file to ensure a fresh start
    if os.path.exists(DB_PATH):
        print(f"--- Removing old database file at {DB_PATH} ---")
        os.remove(DB_PATH)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        try:
            # Step 1: Sign up a new user (who will become admin)
            print("1. Signing up a new user...")
            page.goto(f"{BASE_URL}/signup.php")
            page.locator("#email").fill(USER_EMAIL)
            page.locator("#password").fill(USER_PASSWORD)
            page.locator("#password_confirm").fill(USER_PASSWORD)
            page.get_by_role("button", name="S'inscrire").click()

            # Wait for redirection to login page
            expect(page).to_have_url(f"{BASE_URL}/login.php?success=1", timeout=10000)
            print("   - Signup successful.")

            # Step 2: Log in as the new user
            print("2. Logging in as user...")
            page.locator("#email").fill(USER_EMAIL)
            page.locator("#password").fill(USER_PASSWORD)
            page.get_by_role("button", name="Se connecter").click()

            # Wait for redirection to dashboard
            expect(page).to_have_url(f"{BASE_URL}/dashboard.php", timeout=10000)
            print("   - Login successful.")

            # Step 3: Promote user to admin directly in the database
            print("3. Promoting user to admin in the database...")
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            cursor.execute("UPDATE users SET role = 'admin' WHERE email = ?", (USER_EMAIL,))
            conn.commit()
            conn.close()
            print("   - User promoted to admin.")

            # Step 4: Log out and log back in to get admin privileges
            print("4. Logging out and logging back in as admin...")
            page.goto(f"{BASE_URL}/logout.php")
            page.goto(f"{BASE_URL}/login.php")
            page.locator("#email").fill(USER_EMAIL)
            page.locator("#password").fill(USER_PASSWORD)
            page.get_by_role("button", name="Se connecter").click()
            expect(page).to_have_url(f"{BASE_URL}/dashboard.php", timeout=10000)
            print("   - Admin login successful.")

            # Step 5: Verify "DB Viewer" link is visible
            print("5. Verifying 'DB Viewer' link...")
            db_viewer_link = page.get_by_role("link", name="DB Viewer")
            expect(db_viewer_link).to_be_visible()
            print("   - 'DB Viewer' link is visible.")

            # Step 6: Navigate to a quiz and verify it loads
            print("6. Verifying quiz page loads correctly...")
            # First go to the main page to find a quiz link
            page.goto(f"{BASE_URL}/")

            # Find the first quiz link in the catalog and click it
            first_quiz_link = page.locator("#catalogue a.group").first
            quiz_title = first_quiz_link.locator("h3").inner_text(timeout=10000)
            print(f"   - Navigating to quiz: {quiz_title}")
            first_quiz_link.click()

            # Verify that the quiz runner is present and no error message is shown
            expect(page.locator("#quiz-runner")).to_be_visible(timeout=10000)

            # Check that the error message is NOT visible
            error_message = page.locator("text=Impossible de démarrer le quiz")
            expect(error_message).not_to_be_visible()

            # Check for the question container to be populated
            question_container = page.locator("#question-container h2")
            expect(question_container).to_have_text(lambda s: len(s) > 0, timeout=10000)

            print("   - Quiz page loaded successfully without errors.")

            # Step 7: Take a screenshot
            print(f"7. Taking screenshot and saving to {SCREENSHOT_PATH}...")
            page.screenshot(path=SCREENSHOT_PATH)
            print("   - Screenshot saved.")

        except Exception as e:
            print(f"An error occurred during verification: {e}")
            # Save a screenshot on failure for debugging
            page.screenshot(path="error.png")
        finally:
            browser.close()

if __name__ == "__main__":
    run_verification()