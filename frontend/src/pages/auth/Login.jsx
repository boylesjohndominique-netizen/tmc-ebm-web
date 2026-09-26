import { useState } from "react";
import { useNavigate } from "react-router-dom";

import {
  ArrowRight,
  Eye,
  EyeOff,
  LockKeyhole,
  LogIn,
  Mail,
  ShieldCheck,
  User,
  KeyRound,
  ArrowLeft,
  CheckCircle,
} from "lucide-react";

import { useAuth } from "../../context/AuthContext";

export default function Login() {
  // Forgot password mode
  const [forgotMode, setForgotMode] = useState(false);

  const [showPassword, setShowPassword] = useState(false);

  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const [busy, setBusy] = useState(false);

  const [loginForm, setLoginForm] = useState({
    login: "",
    password: "",
  });

  // Forgot password form
  const [forgotEmail, setForgotEmail] = useState("");

  const { login } = useAuth();

  const navigate = useNavigate();

  // ==========================================
  // SWITCH BETWEEN LOGIN / FORGOT
  // ==========================================

  // Open forgot password form
  const openForgotPassword = () => {
    setForgotMode(true);

    setError("");
    setMessage("");
    setForgotEmail("");
  };

  // Return to login
  const backToLogin = () => {
    setForgotMode(false);

    setError("");
    setMessage("");
  };

  // ==========================================
  // LOGIN
  // ==========================================

  const submitLogin = async (e) => {
    e.preventDefault();

    if (busy) return;

    setError("");
    setMessage("");
    setBusy(true);

    try {
      await login(
        loginForm.login,
        loginForm.password
      );

      // Admin-only system: always land on the admin dashboard.
      navigate("/admin", { replace: true });

    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Login failed. Please check your username/email and password."
      );

    } finally {
      setBusy(false);
    }
  };

  // ==========================================
  // FORGOT PASSWORD
  // ==========================================

  const submitForgotPassword = async (e) => {
    e.preventDefault();

    if (busy) return;

    setError("");
    setMessage("");

    // Basic email validation
    if (!forgotEmail.trim()) {
      setError("Please enter your registered email address.");
      return;
    }

    setBusy(true);

    try {
      const response = await fetch(
        "http://127.0.0.1:8000/api/forgot-password",
        {
          method: "POST",

          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },

          body: JSON.stringify({
            email: forgotEmail.trim(),
          }),
        }
      );

      const data = await response.json();

      if (!response.ok) {
        throw new Error(
          data.message || "Unable to send password reset link."
        );
      }

      setMessage(
        data.message ||
          "If your email is registered, a password reset link has been sent."
      );

      setForgotEmail("");

    } catch (err) {
      setError(
        err.message ||
          "Unable to send password reset link. Please try again."
      );

    } finally {
      setBusy(false);
    }
  };

  // ==========================================
  // INPUT HANDLER
  // ==========================================

  const updateLogin = (e) =>
    setLoginForm((current) => ({
      ...current,
      [e.target.name]: e.target.value,
    }));

  // ==========================================
  // UI
  // ==========================================

  return (
    <main className="auth-page">
      <section className="auth-shell">

        {/* ======================================
            LEFT SIDE
        ====================================== */}

        <aside className="auth-intro">

          <div className="auth-brand-row">

            <div className="auth-logo">
              TMC
            </div>

            <div>
              <strong>
                TRINIDAD MUNICIPAL COLLEGE
              </strong>

              <span>
                College Equipment Services
              </span>
            </div>

          </div>

          <div className="auth-intro-content">

            <span className="auth-kicker">
              WEB-BASED MANAGEMENT SYSTEM
            </span>

            <h1>
              Equipment Borrowing
              <br />
              &amp; Tracking
            </h1>

            <p>
              A centralized web system for requesting,
              monitoring, returning, and managing
              college equipment.
            </p>

            <div className="auth-feature-list">

              <div>
                <ShieldCheck size={19} />
                <span>
                  Secure role-based access
                </span>
              </div>

              <div>
                <ArrowRight size={19} />
                <span>
                  Simple borrowing workflow
                </span>
              </div>

              <div>
                <LockKeyhole size={19} />
                <span>
                  Organized transaction records
                </span>
              </div>

            </div>

          </div>

          <div className="auth-intro-footer">

            <span>
              © {new Date().getFullYear()} Trinidad Municipal College
            </span>

            <span>
              Equipment Borrowing &amp; Tracking Management System
            </span>

          </div>

        </aside>

        {/* ======================================
            RIGHT SIDE
        ====================================== */}

        <section className="auth-form-area">

          <div className="auth-form-card">

            {/* ==================================
                FORGOT PASSWORD FORM
            ================================== */}

            {forgotMode ? (

              <>

                <div className="auth-form-heading">

                  <span className="auth-form-icon">
                    <KeyRound size={22} />
                  </span>

                  <div>

                    <h2>
                      Forgot Password?
                    </h2>

                    <p>
                      Enter your registered email
                      to reset your password.
                    </p>

                  </div>

                </div>

                {error && (
                  <div className="alert error">
                    {error}
                  </div>
                )}

                {message && (
                  <div className="alert success">
                    <CheckCircle size={18} />
                    {message}
                  </div>
                )}

                <form
                  onSubmit={submitForgotPassword}
                  className="auth-form"
                >

                  <label>

                    Registered Email

                    <div className="input-with-icon">

                      <Mail size={18} />

                      <input
                        type="email"
                        name="forgotEmail"
                        value={forgotEmail}
                        onChange={(e) =>
                          setForgotEmail(e.target.value)
                        }
                        placeholder="Enter your registered email"
                        autoComplete="email"
                        required
                      />

                    </div>

                  </label>

                  <p className="forgot-password-help">
                    Enter the email address associated
                    with your TMC account. If the account
                    exists, you will receive instructions
                    to reset your password.
                  </p>

                  <button
                    className="btn btn-primary auth-submit"
                    type="submit"
                    disabled={busy}
                  >

                    <Mail size={18} />

                    {busy
                      ? "Sending Reset Link…"
                      : "Send Reset Link"}

                  </button>

                  <div className="auth-switch">

                    <button
                      type="button"
                      onClick={backToLogin}
                      className="text-button"
                    >

                      <ArrowLeft size={16} />

                      Back to Login

                    </button>

                  </div>

                </form>

              </>

            ) : (

              /* ==================================
                 LOGIN
              ================================== */

              <>

                <div className="auth-form-heading">

                  <span className="auth-form-icon">
                    <LogIn size={22} />
                  </span>

                  <div>

                    <h2>
                      Welcome Back
                    </h2>

                    <p>
                      Sign in to access the admin
                      dashboard.
                    </p>

                  </div>

                </div>

                {error && (
                  <div className="alert error">
                    {error}
                  </div>
                )}

                {message && (
                  <div className="alert success">
                    {message}
                  </div>
                )}

                <form
                  onSubmit={submitLogin}
                  className="auth-form"
                >

                  <label>

                    Username or Email

                    <div className="input-with-icon">

                      <User size={18} />

                      <input
                        name="login"
                        value={loginForm.login}
                        onChange={updateLogin}
                        placeholder="Enter username or email"
                        autoComplete="username"
                        required
                      />

                    </div>

                  </label>

                  <label>

                    Password

                    <div className="password-field input-with-icon">

                      <LockKeyhole size={18} />

                      <input
                        type={
                          showPassword
                            ? "text"
                            : "password"
                        }
                        name="password"
                        value={loginForm.password}
                        onChange={updateLogin}
                        placeholder="Enter your password"
                        autoComplete="current-password"
                        required
                      />

                      <button
                        type="button"
                        className="password-toggle"
                        onClick={() =>
                          setShowPassword(
                            (value) => !value
                          )
                        }
                        aria-label={
                          showPassword
                            ? "Hide password"
                            : "Show password"
                        }
                      >

                        {showPassword ? (
                          <EyeOff size={18} />
                        ) : (
                          <Eye size={18} />
                        )}

                      </button>

                    </div>

                  </label>

                  <div className="auth-options">

                    <span>
                      Use your registered TMC admin account.
                    </span>

                    <button
                      type="button"
                      className="text-button"
                      onClick={openForgotPassword}
                    >
                      Forgot Password?
                    </button>

                  </div>

                  <button
                    className="btn btn-primary auth-submit"
                    type="submit"
                    disabled={busy}
                  >

                    <LogIn size={18} />

                    {busy
                      ? "Signing In…"
                      : "Sign In"}

                  </button>

                </form>

              </>

            )}

          </div>

        </section>

      </section>

    </main>
  );
}
