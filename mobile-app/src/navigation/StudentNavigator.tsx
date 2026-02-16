import { createNativeStackNavigator } from "@react-navigation/stack"
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

import StudentDashboard from "../screens/student/StudentDashboard"
import ExamResults from "../screens/student/ExamResults"
import Timetable from "../screens/student/Timetable"
import Fees from "../screens/student/Fees"
import Clubs from "../screens/student/Clubs"
import ProfileScreen from "../screens/ProfileScreen"

const Stack = createNativeStackNavigator()
const Tab = createBottomTabNavigator()

function DashboardStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: true }}>
      <Stack.Screen name="Dashboard" component={StudentDashboard} />
    </Stack.Navigator>
  )
}

export default function StudentNavigator() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ color, size }) => {
          let iconName

          if (route.name === "DashboardStack") {
            iconName = "dashboard"
          } else if (route.name === "ExamResults") {
            iconName = "assignment"
          } else if (route.name === "Timetable") {
            iconName = "schedule"
          } else if (route.name === "Fees") {
            iconName = "receipt"
          } else if (route.name === "Clubs") {
            iconName = "people"
          } else if (route.name === "Profile") {
            iconName = "person"
          }

          return <MaterialIcons name={iconName} size={size} color={color} />
        },
        tabBarActiveTintColor: "#0066cc",
        tabBarInactiveTintColor: "#999",
        headerShown: true,
      })}
    >
      <Tab.Screen name="DashboardStack" component={DashboardStack} options={{ title: "Dashboard" }} />
      <Tab.Screen name="ExamResults" component={ExamResults} options={{ title: "Exams" }} />
      <Tab.Screen name="Timetable" component={Timetable} options={{ title: "Timetable" }} />
      <Tab.Screen name="Fees" component={Fees} options={{ title: "Fees" }} />
      <Tab.Screen name="Clubs" component={Clubs} options={{ title: "Clubs" }} />
      <Tab.Screen name="Profile" component={ProfileScreen} options={{ title: "Profile" }} />
    </Tab.Navigator>
  )
}
