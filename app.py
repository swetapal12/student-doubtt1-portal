import streamlit as st
import sqlite3
import hashlib
import os
from datetime import datetime

st.set_page_config(page_title="Student Doubt Portal", page_icon="🎓", layout="wide")
DB_PATH = os.environ.get("PORTAL_DB_PATH", "student_doubt_portal.db")


def db():
    conn = sqlite3.connect(DB_PATH, check_same_thread=False)
    conn.row_factory = sqlite3.Row
    return conn


def hash_password(password):
    return hashlib.sha256(password.encode("utf-8")).hexdigest()


def init_db():
    with db() as con:
        con.execute("""CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student'
        )""")
        con.execute("""CREATE TABLE IF NOT EXISTS doubts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            answer TEXT,
            status TEXT NOT NULL DEFAULT 'Pending',
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )""")
        teacher = con.execute("SELECT id FROM users WHERE email=?", ("teacher@portal.local",)).fetchone()
        if not teacher:
            con.execute("INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)",
                        ("Demo Teacher", "teacher@portal.local", hash_password("Teacher@123"), "teacher"))


init_db()
if "user" not in st.session_state:
    st.session_state.user = None


def login(email, password):
    with db() as con:
        row = con.execute("SELECT id,name,email,role,password FROM users WHERE email=?", (email.strip().lower(),)).fetchone()
    if row and row["password"] == hash_password(password):
        st.session_state.user = {"id": row["id"], "name": row["name"], "email": row["email"], "role": row["role"]}
        return True
    return False


def header():
    st.title("🎓 Student Doubt Portal")
    st.caption("Learn together. Grow together. Every question deserves an answer.")
    st.divider()


header()
user = st.session_state.user

if user is None:
    tab1, tab2, tab3 = st.tabs(["Home", "Student Registration", "Login"])
    with tab1:
        st.subheader("Welcome to your learning space")
        st.write("A simple place for students to ask academic questions and teachers to share helpful answers.")
        c1, c2, c3 = st.columns(3)
        c1.info("📝 **Ask doubts**\n\nSubmit questions by subject.")
        c2.info("🧑‍🏫 **Teacher guidance**\n\nGet answers from teachers.")
        c3.info("📊 **Track progress**\n\nSee pending and answered doubts.")
        st.markdown("**Demo teacher login**  \nEmail: `teacher@portal.local`  \nPassword: `Teacher@123`")
    with tab2:
        st.subheader("Create student account")
        with st.form("register_form"):
            name = st.text_input("Full name")
            email = st.text_input("Email address")
            password = st.text_input("Password", type="password")
            confirm = st.text_input("Confirm password", type="password")
            submitted = st.form_submit_button("Create account", use_container_width=True)
        if submitted:
            if not name.strip() or not email.strip() or not password:
                st.error("Please fill in all fields.")
            elif password != confirm:
                st.error("Passwords do not match.")
            elif len(password) < 6:
                st.error("Use a password with at least 6 characters.")
            else:
                try:
                    with db() as con:
                        con.execute("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'student')",
                                    (name.strip(), email.strip().lower(), hash_password(password)))
                    st.success("Account created. Open the Login tab to sign in.")
                except sqlite3.IntegrityError:
                    st.error("This email is already registered.")
    with tab3:
        st.subheader("Login")
        with st.form("login_form"):
            email = st.text_input("Email", key="login_email")
            password = st.text_input("Password", type="password", key="login_password")
            submitted = st.form_submit_button("Login", use_container_width=True)
        if submitted:
            if login(email, password):
                st.rerun()
            else:
                st.error("Incorrect email or password.")
else:
    top1, top2 = st.columns([4, 1])
    top1.markdown(f"### Welcome, {user['name']}  ")
    top1.caption(f"{user['role'].title()} workspace · {user['email']}")
    if top2.button("Logout", use_container_width=True):
        st.session_state.user = None
        st.rerun()

    if user["role"] == "student":
        st.subheader("Ask a new doubt")
        with st.form("ask_form", clear_on_submit=True):
            subject = st.selectbox("Subject", ["Mathematics", "Science", "Computer Science", "English", "Other"])
            title = st.text_input("Doubt title")
            description = st.text_area("Describe your doubt", height=130)
            submitted = st.form_submit_button("Submit doubt", use_container_width=True)
        if submitted:
            if not title.strip() or not description.strip():
                st.error("Please enter both a title and description.")
            else:
                with db() as con:
                    con.execute("INSERT INTO doubts(user_id,subject,title,description,status,created_at) VALUES(?,?,?,?,?,?)",
                                (user["id"], subject, title.strip(), description.strip(), "Pending", datetime.now().strftime("%d %b %Y, %I:%M %p")))
                st.success("Your doubt has been submitted.")
                st.rerun()

        with db() as con:
            doubts = con.execute("SELECT * FROM doubts WHERE user_id=? ORDER BY id DESC", (user["id"],)).fetchall()
        answered = sum(1 for d in doubts if d["status"] == "Answered")
        a, b, c = st.columns(3)
        a.metric("Total doubts", len(doubts))
        b.metric("Pending", len(doubts) - answered)
        c.metric("Answered", answered)
        st.subheader("Your doubts")
        if not doubts:
            st.info("You haven't submitted any doubts yet.")
        for d in doubts:
            with st.container(border=True):
                st.markdown(f"**{d['title']}**  ·  `{d['subject']}`")
                st.caption(f"{d['created_at']} · {d['status']}")
                st.write(d["description"])
                if d["answer"]:
                    st.success("Teacher's answer")
                    st.write(d["answer"])
                else:
                    st.warning("Waiting for teacher's answer")
    else:
        with db() as con:
            doubts = con.execute("""SELECT d.*,u.name AS student_name,u.email AS student_email
                FROM doubts d JOIN users u ON u.id=d.user_id
                ORDER BY CASE WHEN d.status='Pending' THEN 0 ELSE 1 END, d.id DESC""").fetchall()
        answered = sum(1 for d in doubts if d["status"] == "Answered")
        a, b, c = st.columns(3)
        a.metric("Total doubts", len(doubts))
        b.metric("Pending", len(doubts) - answered)
        c.metric("Answered", answered)
        st.subheader("All student doubts")
        if not doubts:
            st.info("No doubts have been submitted yet.")
        for d in doubts:
            with st.container(border=True):
                st.markdown(f"**{d['title']}**  ·  `{d['subject']}`")
                st.caption(f"By {d['student_name']} ({d['student_email']}) · {d['created_at']} · {d['status']}")
                st.write(d["description"])
                with st.form(f"answer_{d['id']}"):
                    answer = st.text_area("Teacher's answer", value=d["answer"] or "", key=f"answer_text_{d['id']}", height=100)
                    submitted = st.form_submit_button("Save answer", use_container_width=False)
                if submitted:
                    if not answer.strip():
                        st.error("Please write an answer before saving.")
                    else:
                        with db() as con:
                            con.execute("UPDATE doubts SET answer=?,status='Answered' WHERE id=?", (answer.strip(), d["id"]))
                        st.success("Answer saved.")
                        st.rerun()

st.divider()
st.caption("Student Doubt Portal · Streamlit edition")
