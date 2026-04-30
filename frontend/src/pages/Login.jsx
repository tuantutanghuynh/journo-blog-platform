import { useState } from "react";
import api from "../api/axios";

export default function Login() {
  // store form input values
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  // store error message if login fails
  const [error, setError] = useState(null);

  // store loading state while waiting for API response
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event) => {
    // prevent page reload on form submit
    event.preventDefault();

    // start loading
    setLoading(true);

    // reset error
    setError(null);

    try {
      // call POST /api/login
      const res = await api.post("/login", {
        email: email,
        password: password,
      });

      // get token from response
      const token = res.data.token;

      // save token to localStorage
      localStorage.setItem("token", token);

      // redirect to home page
      window.location.href = "/";
    } catch (err) {
      // show error message if login fails
      setError("Invalid email or password");
    } finally {
      // always stop loading whether success or fail
      setLoading(false);
    }
  };

  return (
    <div>
      <h1>Login</h1>

      {/* show error message if exists */}
      {error && <p style={{ color: "red" }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>

        <div>
          <label>Password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? "Logging in..." : "Login"}
        </button>
      </form>
    </div>
  );
}
