import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import api from "../api/axios";

export default function PosDetail() {
  //get post ID from URL -e.g/posts/1 -> id ="1"
  const { id } = useParams();

  //store the post datta
  const [post, setPost] = useState(null);

  //store comment
  const [comments, setComments] = useState([]);

  //store loading and error
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  //fetch post and comments when page load
  useEffect(() => {
    fetchPost();
    fetchComments();
  }, []);

  const fetchPost = async () => {
    try {
      const res = await api.get(`/posts/${id}`);
      setPost(res.data);
    } catch (err) {
      setError("Post not foun");
    } finally {
      setLoading(false);
    }
  };

  const fetchComments = async () => {
    try {
      const res = await api.get(`/postts/${id}/comments`);
      setComments(res.data);
    } catch (err) {
      //silently fail - comment not critical
    }
  };

  if (loading) {
    return <p>Loading....</p>;
  }
  if (error) {
    return <p style={{ color: "red" }}>{error}</p>;
  }

  return (
    <div>
      <h1>{post.title}</h1>
      <p>By: {post.author?.name}</p>
      <p>Category: {post.category?.name}</p>
      <hr />

      <p>{post.content}</p>
      <hr />
      <h2>Comments ({comments.length})</h2>
      {comments.length === 0 ? (
        <p>No comments yet</p>
      ) : (
        comments.map((comment) => (
          <div key={comment.id}>
            <p>
              <strong>{comment.user?.name}</strong>: {comment.content}
            </p>
          </div>
        ))
      )}
    </div>
  );
}
