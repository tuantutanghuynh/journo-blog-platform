import axios from "axios";

//axios instance with baseURL set to our backend API
const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
});

//automatically attach token to every request if user is logged in
api.interceptors.request.use((config) => {
  //get token from local storage
  const token = localStorage.getItem("token");

  //if exisst, add to Authorization header
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
