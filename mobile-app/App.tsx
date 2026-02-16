"use client"

import React, { useEffect } from "react"
import { NavigationContainer } from "@react-navigation/native"
import { createNativeStackNavigator } from "@react-navigation/stack"
import { ActivityIndicator, View } from "react-native"
import * as SecureStore from "expo-secure-store"
import LoginScreen from "./src/screens/LoginScreen"
import StudentNavigator from "./src/navigation/StudentNavigator"
import TeacherNavigator from "./src/navigation/TeacherNavigator"
import AdminNavigator from "./src/navigation/AdminNavigator"

const Stack = createNativeStackNavigator()

export default function App() {
  const [state, dispatch] = React.useReducer(
    (prevState, action) => {
      switch (action.type) {
        case "RESTORE_TOKEN":
          return {
            ...prevState,
            userToken: action.token,
            userRole: action.role,
            isLoading: false,
          }
        case "SIGN_IN":
          return {
            ...prevState,
            isSignout: false,
            userToken: action.token,
            userRole: action.role,
          }
        case "SIGN_OUT":
          return {
            ...prevState,
            isSignout: true,
            userToken: null,
            userRole: null,
          }
      }
    },
    {
      isLoading: true,
      isSignout: false,
      userToken: null,
      userRole: null,
    },
  )

  useEffect(() => {
    const bootstrapAsync = async () => {
      let userToken
      let userRole
      try {
        userToken = await SecureStore.getItemAsync("userToken")
        userRole = await SecureStore.getItemAsync("userRole")
      } catch (e) {
        console.error(e)
      }

      dispatch({ type: "RESTORE_TOKEN", token: userToken, role: userRole })
    }

    bootstrapAsync()
  }, [])

  if (state.isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {state.userToken == null ? (
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : state.userRole === "Student" ? (
          <Stack.Screen name="StudentApp" component={StudentNavigator} />
        ) : state.userRole === "Teacher" ? (
          <Stack.Screen name="TeacherApp" component={TeacherNavigator} />
        ) : (
          <Stack.Screen name="AdminApp" component={AdminNavigator} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  )
}
