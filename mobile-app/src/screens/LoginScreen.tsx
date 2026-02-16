"use client"

import { useState } from "react"
import { View, TextInput, TouchableOpacity, Text, StyleSheet, Alert, ScrollView, ActivityIndicator } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws" // Replace with your actual domain

export default function LoginScreen({ navigation }) {
  const [username, setUsername] = useState("")
  const [password, setPassword] = useState("")
  const [loading, setLoading] = useState(false)

  const handleLogin = async () => {
    if (!username || !password) {
      Alert.alert("Error", "Please fill in all fields")
      return
    }

    setLoading(true)
    try {
      const response = await axios.post(`${API_URL}/api/login.php`, {
        username,
        password,
      })

      if (response.data.success) {
        await SecureStore.setItemAsync("userToken", response.data.token)
        await SecureStore.setItemAsync("userRole", response.data.role)
        await SecureStore.setItemAsync("userId", response.data.userId)
        await SecureStore.setItemAsync("userName", response.data.fullName)
      } else {
        Alert.alert("Error", response.data.message || "Login failed")
      }
    } catch (error) {
      Alert.alert("Error", "Network error. Please check your connection.")
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.logoContainer}>
        <Text style={styles.title}>School Management System</Text>
        <Text style={styles.subtitle}>v22 Mobile App</Text>
      </View>

      <View style={styles.form}>
        <TextInput
          style={styles.input}
          placeholder="Username or Email"
          placeholderTextColor="#999"
          value={username}
          onChangeText={setUsername}
          editable={!loading}
        />

        <TextInput
          style={styles.input}
          placeholder="Password"
          placeholderTextColor="#999"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          editable={!loading}
        />

        <TouchableOpacity
          style={[styles.button, loading && styles.buttonDisabled]}
          onPress={handleLogin}
          disabled={loading}
        >
          {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Login</Text>}
        </TouchableOpacity>

        <View style={styles.helpText}>
          <Text style={styles.helpTitle}>Login Credentials:</Text>
          <Text style={styles.help}>Students: Use full name</Text>
          <Text style={styles.help}>Teachers/Admin: Use username/email</Text>
          <Text style={styles.help}>Password: student.grade.year (e.g., student.9.2025)</Text>
        </View>
      </View>
    </ScrollView>
  )
}

const styles = StyleSheet.create({
  container: {
    flexGrow: 1,
    backgroundColor: "#f5f5f5",
    paddingHorizontal: 20,
    justifyContent: "center",
  },
  logoContainer: {
    alignItems: "center",
    marginBottom: 40,
  },
  title: {
    fontSize: 28,
    fontWeight: "bold",
    color: "#0066cc",
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 14,
    color: "#666",
  },
  form: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 20,
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 10,
    elevation: 5,
  },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    paddingHorizontal: 15,
    paddingVertical: 12,
    marginBottom: 15,
    fontSize: 16,
    color: "#333",
  },
  button: {
    backgroundColor: "#0066cc",
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: "center",
    marginTop: 10,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  helpText: {
    marginTop: 20,
    paddingTop: 20,
    borderTopWidth: 1,
    borderTopColor: "#eee",
  },
  helpTitle: {
    fontWeight: "600",
    color: "#333",
    marginBottom: 8,
  },
  help: {
    color: "#666",
    fontSize: 13,
    marginBottom: 4,
  },
})
