# LMS Manual Testing Guide

This guide outlines step-by-step procedures to verify the functionality of the new Course Builder, Student Dashboard, Quiz Enrollment Rules, and Progress Tracking features.

## Prerequisites

- Access to the WordPress Admin Dashboard.
- A user account with `Administrator` role (for Instructor Hub).
- A user account with `Subscriber` role (for Student Hub testing).
- `smc-viable` plugin active.
- Access to the site's email inbox (or use WP Mail Logging plugin for local testing).

---

## 1. Instructor Hub: Course Management

### 1.1 Create a Standalone Course
1.  Navigate to **Instructor Hub** -> **Builder**.
2.  Click **"Create New Course"**.
3.  Enter Title: "Test Standalone Course".
4.  Select Access Type: **"Standalone (Purchase/Enroll)"**.
5.  Click **"Create Course"**.
6.  **Expected Result:** Course appears in the list with a "Standalone" badge. Students count should be 0.Lessons count 0.

### 1.2 Create a Plan-Linked Course
1.  Click **"Create New Course"**.
2.  Enter Title: "Test Premium Course".
3.  Select Access Type: **"Plan Access (Membership)"**.
4.  Select Minimum Plan Level: **"Premium Plan"**.
5.  Click **"Create Course"**.
6.  **Expected Result:** Course appears in list with "Plan: Premium" badge.

### 1.3 Edit Course Structure
1.  Click **"Structure"** on "Test Standalone Course".
2.  Click **"Add Section"**, name it "Module 1".
3.  Click **"New Lesson"** inside Module 1.
4.  Enter Lesson Title: "Intro Video".
5.  **Expected Result:** A new tab opens with the WordPress editor.
6.  Add a Video block with a YouTube URL. Save/Update.
7.  Close tab and return to Structure Editor. Click **"Refresh"** (or reload).
8.  **Expected Result:** "Intro Video" appears under Module 1 with a **Video icon** (🎥).
9.  Click **"Add Lesson"** -> Search for "Intro Video". Select it.
10. **Expected Result:** The lesson is attached again (deduplication allowed).

---

## 2. Quiz Enrollment Rules

### 2.1 Define Rules
1.  Navigate to **Instructor Hub** -> **Quiz Rules**.
2.  Select a Quiz from the sidebar (Create one in WP Admin -> Quizzes if none exist).
3.  Click **"Add Rule"**.
4.  Set Condition: `Greater or Equal (>=) 50`.
5.  Check "Test Standalone Course" in the course list.
6.  Click **"Save Rules"**.
7.  **Expected Result:** Alert "Rules saved!".

---

## 3. Student Experience: Quiz Integration

### 3.1 Anonymous User Flow
1.  Open the site in an Incognito window (not logged in).
2.  Navigate to the Quiz page.
3.  Complete the quiz with a score >= 50.
4.  Submit the lead form (Name/Email).
5.  **Expected Result:** 
    - Results Dashboard appears.
    - "Modules Unlocked!" section shows "Exclusive Content".
    - Because you are anonymous, it might prompt to "Log in to save progress" (if configured) or just show the results. *Note: Actual enrollment only happens for logged-in users or via the email invite link (if implemented).*

### 3.2 Logged-In User Flow
1.  Log in as a **Subscriber** (with no prior enrollments).
2.  Take the same quiz. Score >= 50.
3.  View Results Dashboard.
4.  **Expected Result:**
    - "Modules Unlocked!" section appears.
    - "Test Standalone Course" is listed.
    - Check email inbox for "Course Enrollment" notification.

---

## 4. Student Hub: Dashboard & Player

### 4.1 Dashboard
1.  Navigate to `/student-hub` (or wherever `App.jsx` is mounted).
2.  **Expected Result:**
    - "Test Standalone Course" appears in the grid.
    - Status: "0% Progress".
    - Button: "BEGIN MODULE".
    - "Test Premium Course" (from step 1.2) should NOT appear (unless you have Premium plan). *Gap Note: We need to verify if locked courses show as upsells.*

### 4.2 Course Player
1.  Click "BEGIN MODULE" on "Test Standalone Course".
2.  **Expected Result:** Player opens. Sidebar lists "Module 1 > Intro Video".
3.  Click "Intro Video".
4.  **Expected Result:** Video renders.
5.  Click **"Complete & Next"**.
6.  **Expected Result:**
    - Sidebar checkmark turns green (✅).
    - "Course Completed!" alert (if it was the only lesson).
    - Redirects to Dashboard or allows exit.

### 4.3 Dashboard Update
1.  Return to Dashboard.
2.  **Expected Result:**
    - "Test Standalone Course" status: "100% Progress" (or 50% if you added 2 lessons).
    - Status indicator: Cancel checkmark / Green bar.
    - Button: "REVIEW MODULE".

---

## 5. Manual Enrollment (Instructor)

1.  Navigate to **Instructor Hub** -> **Students**.
2.  Click **"Invite Student"**.
3.  Enter a *new* email address.
4.  Select "Test Standalone Course".
5.  Click **"Send Invitation"**.
6.  **Expected Result:**
    - Success message.
    - Student appears in the directory list.
    - Email inbox receives an invitation with a magic login link.

---

## Edge Case Checklist

- [ ] **Plan Downgrade:** Change user role/plan to "Free". Access to "Test Premium Course" should be revoked (403 error or locked icon).
- [ ] **Quiz Retake:** Retaking quiz with lower score shouldn't revoke access.
- [ ] **Duplicate Invite:** Inviting an existing user should just enroll them, not create a new user.
