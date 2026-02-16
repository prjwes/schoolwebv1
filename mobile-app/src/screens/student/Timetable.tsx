"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert, Dimensions } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws"
const { width } = Dimensions.get("window")

export default function Timetable() {
  const [timetable, setTimetable] = useState(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchTimetable()
  }, [])

  const fetchTimetable = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/student/timetable.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setTimetable(response.data.timetable)
      }
    } catch (error) {
      console.error("Error fetching timetable:", error)
      Alert.alert("Error", "Failed to load timetable")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchTimetable()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      horizontal
    >
      {days.map((day) => (
        <View key={day} style={styles.dayColumn}>
          <Text style={styles.dayHeader}>{day}</Text>
          {timetable && timetable[day] ? (
            timetable[day].map((session, idx) => (
              <View key={idx} style={styles.sessionCard}>
                <Text style={styles.time}>{session.time}</Text>
                <Text style={styles.subject}>{session.subject}</Text>
                <Text style={styles.teacher}>{session.teacher}</Text>
                <Text style={styles.room}>{session.room}</Text>
              </View>
            ))
          ) : (
            <Text style={styles.noSession}>No classes</Text>
          )}
        </View>
      ))}
    </ScrollView>
  )
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  centerContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  dayColumn: {
    width: width - 30,
    marginHorizontal: 15,
    marginVertical: 10,
  },
  dayHeader: {
    fontSize: 18,
    fontWeight: "bold",
    color: "#0066cc",
    marginBottom: 12,
  },
  sessionCard: {
    backgroundColor: "#fff",
    borderRadius: 8,
    padding: 12,
    marginBottom: 10,
    borderLeftWidth: 4,
    borderLeftColor: "#0066cc",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  time: {
    fontSize: 12,
    color: "#999",
    fontWeight: "500",
  },
  subject: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginVertical: 4,
  },
  teacher: {
    fontSize: 13,
    color: "#666",
  },
  room: {
    fontSize: 12,
    color: "#999",
    marginTop: 4,
  },
  noSession: {
    fontSize: 14,
    color: "#999",
    textAlign: "center",
    marginTop: 20,
  },
})
