"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws"

export default function ExamResults() {
  const [exams, setExams] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchExamResults()
  }, [])

  const fetchExamResults = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/student/exam-results.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setExams(response.data.exams || [])
      }
    } catch (error) {
      console.error("Error fetching exams:", error)
      Alert.alert("Error", "Failed to load exam results")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchExamResults()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {exams.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyText}>No exam results available</Text>
        </View>
      ) : (
        <View style={styles.examsContainer}>
          {exams.map((exam) => (
            <View key={exam.id} style={styles.examCard}>
              <View style={styles.examHeader}>
                <Text style={styles.examName}>{exam.exam_name}</Text>
                <Text style={styles.examDate}>{exam.exam_date}</Text>
              </View>

              <View style={styles.marksContainer}>
                {exam.subjects &&
                  exam.subjects.map((subject, idx) => (
                    <View key={idx} style={styles.markRow}>
                      <Text style={styles.subjectName}>{subject.name}</Text>
                      <Text style={[styles.mark, getMarkColor(subject.marks)]}>{subject.marks}</Text>
                    </View>
                  ))}
              </View>

              <View style={styles.examFooter}>
                <Text style={styles.footerText}>Average: {exam.average || "N/A"}</Text>
                <Text style={styles.footerText}>Grade: {exam.grade || "N/A"}</Text>
              </View>
            </View>
          ))}
        </View>
      )}
    </ScrollView>
  )
}

function getMarkColor(marks) {
  if (marks >= 80) return { color: "#28a745" }
  if (marks >= 60) return { color: "#ffc107" }
  return { color: "#dc3545" }
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
  examsContainer: {
    padding: 15,
  },
  examCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    marginBottom: 15,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  examHeader: {
    backgroundColor: "#0066cc",
    paddingHorizontal: 15,
    paddingVertical: 12,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  examName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#fff",
    flex: 1,
  },
  examDate: {
    fontSize: 12,
    color: "#e0e0e0",
  },
  marksContainer: {
    paddingHorizontal: 15,
    paddingVertical: 12,
  },
  markRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: "#eee",
  },
  subjectName: {
    fontSize: 14,
    color: "#333",
    flex: 1,
  },
  mark: {
    fontSize: 14,
    fontWeight: "600",
  },
  examFooter: {
    backgroundColor: "#f9f9f9",
    paddingHorizontal: 15,
    paddingVertical: 10,
    flexDirection: "row",
    justifyContent: "space-between",
    borderTopWidth: 1,
    borderTopColor: "#eee",
  },
  footerText: {
    fontSize: 13,
    color: "#666",
    fontWeight: "500",
  },
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingTop: 100,
  },
  emptyText: {
    fontSize: 16,
    color: "#999",
  },
})
